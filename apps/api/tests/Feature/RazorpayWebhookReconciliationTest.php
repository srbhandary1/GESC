<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\CheckoutCapture;
use App\Billing\CheckoutOrder;
use App\Billing\InvalidPaymentWebhook;
use App\Billing\PaymentProvider;
use App\Billing\RazorpayWebhookProcessor;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class RazorpayWebhookReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret-1234567890']);
        $this->app->instance(PaymentProvider::class, new class implements PaymentProvider
        {
            public function publicKey(): string
            {
                return 'rzp_test_public123456';
            }

            public function capture(CheckoutOrder $order): CheckoutCapture
            {
                return new CheckoutCapture('pending', 'razorpay', 'order_webhook123456', null, $this->publicKey());
            }

            public function confirm(
                CheckoutOrder $order,
                string $providerReference,
                string $providerPaymentReference,
                string $signature,
            ): CheckoutCapture {
                return $this->reconcile($order, $providerReference, $providerPaymentReference);
            }

            public function reconcile(
                CheckoutOrder $order,
                string $providerReference,
                ?string $providerPaymentReference = null,
            ): CheckoutCapture {
                return new CheckoutCapture(
                    'completed',
                    'razorpay',
                    $providerReference,
                    $providerPaymentReference ?? 'pay_webhook123456',
                    $this->publicKey(),
                );
            }
        });
    }

    public function test_signed_captured_webhook_grants_once_and_replay_is_idempotent(): void
    {
        [$learner, $context] = $this->learner();
        $manager = app(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $planId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::DEMO)
                ->value('id');
            self::assertIsString($planId);
            $pending = app(LearnerSubscriptionService::class)->checkout(
                $learner,
                $planId,
                (string) Str::ulid(),
            );
            self::assertSame('pending', $pending['status']);

            $raw = json_encode([
                'event' => 'payment.captured',
                'payload' => [
                    'payment' => ['entity' => [
                        'id' => 'pay_webhook123456',
                        'order_id' => 'order_webhook123456',
                    ]],
                ],
            ], JSON_THROW_ON_ERROR);
            $signature = hash_hmac('sha256', $raw, 'webhook-secret-1234567890');
            $processor = app(RazorpayWebhookProcessor::class);
            $processor->process($raw, $signature, 'event_webhook_1');
            $processor->process($raw, $signature, 'event_webhook_1');

            self::assertSame(1, DB::table('learner_entitlements')->count());
            self::assertSame(1, DB::table('payment_webhook_events')->count());
            self::assertSame(
                'processed',
                DB::table('payment_webhook_events')->value('status'),
            );
            self::assertSame(
                'completed',
                DB::table('subscription_checkouts')->value('status'),
            );
        } finally {
            $manager->clear();
        }
    }

    public function test_invalid_signature_writes_no_receipt_or_entitlement(): void
    {
        $raw = '{"event":"payment.captured","payload":{}}';

        $this->expectException(InvalidPaymentWebhook::class);
        try {
            app(RazorpayWebhookProcessor::class)->process(
                $raw,
                str_repeat('0', 64),
                'event_tampered',
            );
        } finally {
            self::assertSame(0, DB::table('payment_webhook_events')->count());
            self::assertSame(0, DB::table('learner_entitlements')->count());
        }
    }

    public function test_signed_malformed_payload_is_rejected_without_a_receipt(): void
    {
        $raw = '{not-json';

        $this->expectException(InvalidPaymentWebhook::class);
        try {
            app(RazorpayWebhookProcessor::class)->process(
                $raw,
                hash_hmac('sha256', $raw, 'webhook-secret-1234567890'),
                'event_malformed',
            );
        } finally {
            self::assertSame(0, DB::table('payment_webhook_events')->count());
        }
    }

    public function test_failed_payment_event_does_not_terminally_fail_a_reusable_order(): void
    {
        [$learner, $context] = $this->learner();
        $manager = app(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $planId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::DEMO)
                ->value('id');
            self::assertIsString($planId);
            app(LearnerSubscriptionService::class)->checkout(
                $learner,
                $planId,
                (string) Str::ulid(),
            );
            $raw = json_encode([
                'event' => 'payment.failed',
                'payload' => [
                    'payment' => ['entity' => [
                        'id' => 'pay_failed123456',
                        'order_id' => 'order_webhook123456',
                    ]],
                ],
            ], JSON_THROW_ON_ERROR);

            app(RazorpayWebhookProcessor::class)->process(
                $raw,
                hash_hmac('sha256', $raw, 'webhook-secret-1234567890'),
                'event_failed_1',
            );

            self::assertSame('pending', DB::table('subscription_checkouts')->value('status'));
            self::assertSame('processed', DB::table('payment_webhook_events')->value('status'));
            self::assertSame(0, DB::table('learner_entitlements')->count());
        } finally {
            $manager->clear();
        }
    }

    public function test_ambiguous_order_creation_is_durable_and_replay_does_not_create_twice(): void
    {
        $provider = new class implements PaymentProvider
        {
            public int $captureCalls = 0;

            public function publicKey(): string
            {
                return 'rzp_test_public123456';
            }

            public function capture(CheckoutOrder $order): CheckoutCapture
            {
                $this->captureCalls++;
                throw new RuntimeException('Simulated response loss after request dispatch.');
            }

            public function confirm(
                CheckoutOrder $order,
                string $providerReference,
                string $providerPaymentReference,
                string $signature,
            ): CheckoutCapture {
                throw new RuntimeException('Not expected.');
            }

            public function reconcile(
                CheckoutOrder $order,
                string $providerReference,
                ?string $providerPaymentReference = null,
            ): CheckoutCapture {
                throw new RuntimeException('Not expected.');
            }
        };
        $this->app->instance(PaymentProvider::class, $provider);
        [$learner, $context] = $this->learner();
        $manager = app(OrganizationContextManager::class);
        $manager->activate($context);

        try {
            $planId = DB::table('entitlement_plans')
                ->where('organization_id', $context->organizationId)
                ->where('code', DirectPlanDefaults::DEMO)
                ->value('id');
            self::assertIsString($planId);
            $idempotencyKey = (string) Str::ulid();
            $service = app(LearnerSubscriptionService::class);

            $first = $service->checkout($learner, $planId, $idempotencyKey);
            $replay = $service->checkout($learner, $planId, $idempotencyKey);

            self::assertSame('pending', $first['status']);
            self::assertFalse($first['replayed']);
            self::assertTrue($replay['replayed']);
            self::assertSame(1, $provider->captureCalls);
            self::assertSame(1, DB::table('subscription_checkouts')->count());
            self::assertSame(0, DB::table('learner_entitlements')->count());
        } finally {
            $manager->clear();
        }
    }

    public function test_another_learner_cannot_read_or_confirm_a_pending_checkout(): void
    {
        [$owner, $ownerContext] = $this->learner();
        [$other, $otherContext] = $this->learner();
        $manager = app(OrganizationContextManager::class);
        $manager->activate($ownerContext);

        try {
            $planId = DB::table('entitlement_plans')
                ->where('organization_id', $ownerContext->organizationId)
                ->where('code', DirectPlanDefaults::DEMO)
                ->value('id');
            self::assertIsString($planId);
            $pending = app(LearnerSubscriptionService::class)->checkout(
                $owner,
                $planId,
                (string) Str::ulid(),
            );
            self::assertIsString($pending['id']);

            $manager->clear();
            $manager->activate($otherContext);
            $service = app(LearnerSubscriptionService::class);
            foreach (['read', 'write'] as $operation) {
                try {
                    if ($operation === 'read') {
                        $service->reconcileCheckout($other, $pending['id']);
                    } else {
                        $service->confirmCheckout(
                            $other,
                            $pending['id'],
                            'pay_crossTenant123',
                            str_repeat('a', 64),
                        );
                    }
                    self::fail("Cross-learner {$operation} must be denied.");
                } catch (EntitlementDenied $denied) {
                    self::assertSame(
                        EntitlementDenied::CHECKOUT_UNAVAILABLE,
                        $denied->messageKey,
                    );
                }
            }
            self::assertSame('pending', DB::table('subscription_checkouts')->value('status'));
            self::assertSame(0, DB::table('learner_entitlements')->count());
        } finally {
            $manager->clear();
        }
    }

    /** @return array{User, OrganizationContext} */
    private function learner(): array
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = User::query()->forceCreate([
            'display_name' => 'Payment learner',
            'status' => User::STATUS_ACTIVE,
        ]);
        $membershipId = (string) Str::ulid();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => Membership::TYPE_LEARNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')
            ->where('organization_id', $organization->getKey())
            ->where('key', 'learner')
            ->value('id');
        self::assertIsString($roleId);
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $user->getKey(),
            membershipId: $membershipId,
            source: OrganizationContextSource::DirectProvisioning,
        )];
    }
}
