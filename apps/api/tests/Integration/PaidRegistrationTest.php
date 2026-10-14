<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Billing\CheckoutCapture;
use App\Billing\CheckoutOrder;
use App\Billing\PaymentProvider;
use App\Entitlements\DirectPlanDefaults;
use App\Models\PasswordCredential;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaidRegistrationTest extends TenancyIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
        config()->set('authentication.mode', 'password_only');
        config()->set('billing.driver', 'dummy');
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_successful_payment_creates_the_account_and_grants_that_plan(): void
    {
        $planId = $this->planId(DirectPlanDefaults::MONTHLY_MODULES);
        $before = User::query()->count();

        $response = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations', [
                'phone' => '74390 91943',
                'password' => 'Correct horse battery 2026!',
                'plan_id' => $planId,
                'idempotency_key' => (string) Str::ulid(),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed');

        self::assertSame($before + 1, User::query()->count());
        $user = User::query()->where('username', '7439091943')->sole();
        self::assertSame((string) $user->getKey(), $response->json('user.id'));
        self::assertNotNull($response->getCookie((string) config('session.cookie')));
        $credential = PasswordCredential::query()->whereKey($user->getKey())->sole();
        self::assertSame('argon2id', $credential->algorithm);
        self::assertNull(DB::table('paid_registration_intents')->value('password_hash'));
        self::assertSame(
            DirectPlanDefaults::MONTHLY_MODULES,
            json_decode((string) DB::table('learner_entitlements')->where('status', 'active')->value('plan_snapshot'), true)['code'] ?? null,
        );

        $this->postJson('/api/v1/auth/password/sign-ins', [
            'username' => '7439091943',
            'password' => 'Correct horse battery 2026!',
        ])->assertOk();
    }

    public function test_pending_payment_creates_the_account_only_after_confirmation(): void
    {
        $this->app->instance(PaymentProvider::class, new class implements PaymentProvider
        {
            public function publicKey(): string
            {
                return 'rzp_test_public123456';
            }

            public function capture(CheckoutOrder $order): CheckoutCapture
            {
                return new CheckoutCapture('pending', 'razorpay', 'order_signup123456', null, $this->publicKey());
            }

            public function confirm(
                CheckoutOrder $order,
                string $providerReference,
                string $providerPaymentReference,
                string $signature,
            ): CheckoutCapture {
                return new CheckoutCapture(
                    'completed',
                    'razorpay',
                    $providerReference,
                    $providerPaymentReference,
                    $this->publicKey(),
                );
            }

            public function reconcile(
                CheckoutOrder $order,
                string $providerReference,
                ?string $providerPaymentReference = null,
            ): CheckoutCapture {
                return $this->confirm(
                    $order,
                    $providerReference,
                    $providerPaymentReference ?? 'pay_signup123456',
                    'ignored',
                );
            }
        });
        $planId = $this->planId(DirectPlanDefaults::MONTHLY_ROLEPLAY);
        $before = User::query()->count();

        $pending = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations', [
                'phone' => '7439091944',
                'password' => 'Correct horse battery 2026!',
                'plan_id' => $planId,
                'idempotency_key' => (string) Str::ulid(),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('total_paise', 59000);

        self::assertSame($before, User::query()->count());
        $checkoutId = $pending->json('id');
        self::assertIsString($checkoutId);

        $confirmed = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations/'.$checkoutId.'/confirmations', [
                'payment_id' => 'pay_signup123456',
                'signature' => str_repeat('ab', 32),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        self::assertSame($before + 1, User::query()->count());
        self::assertNotNull($confirmed->getCookie((string) config('session.cookie')));
        self::assertSame(
            DirectPlanDefaults::MONTHLY_ROLEPLAY,
            json_decode((string) DB::table('learner_entitlements')->where('status', 'active')->value('plan_snapshot'), true)['code'] ?? null,
        );
    }

    public function test_rejected_password_does_not_create_an_account(): void
    {
        $before = User::query()->count();

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations', [
                'phone' => '9876543210',
                'password' => 'short',
                'plan_id' => $this->planId(DirectPlanDefaults::MONTHLY_ROLEPLAY),
                'idempotency_key' => (string) Str::ulid(),
            ])
            ->assertUnprocessable();

        self::assertSame($before, User::query()->count());
        self::assertSame(0, DB::table('subscription_checkouts')->count());
    }

    public function test_existing_phone_receives_the_paid_plan_without_a_second_account(): void
    {
        $planId = $this->planId(DirectPlanDefaults::MONTHLY_ROLEPLAY);
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations', [
                'phone' => '9000000001',
                'password' => 'Correct horse battery 2026!',
                'plan_id' => $this->planId(DirectPlanDefaults::MONTHLY_MODULES),
                'idempotency_key' => (string) Str::ulid(),
            ])
            ->assertCreated();

        $count = User::query()->count();
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations', [
                'phone' => '9000000001',
                'password' => 'Correct horse battery 2026!',
                'plan_id' => $planId,
                'idempotency_key' => (string) Str::ulid(),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed');

        self::assertSame($count, User::query()->count());
        self::assertSame(
            DirectPlanDefaults::MONTHLY_ROLEPLAY,
            json_decode((string) DB::table('learner_entitlements')->where('status', 'active')->value('plan_snapshot'), true)['code'] ?? null,
        );
    }

    public function test_registration_replay_is_bound_to_the_original_phone_and_valid_password(): void
    {
        $body = ['phone' => '9000000021', 'password' => 'Correct horse battery 2026!',
            'plan_id' => $this->planId(DirectPlanDefaults::MONTHLY_MODULES),
            'idempotency_key' => (string) Str::ulid()];
        $first = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations', $body)->assertCreated();
        $this->postJson('/api/v1/direct/paid-registrations', $body)
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('id', $first->json('id'));
        $this->postJson('/api/v1/direct/paid-registrations', [...$body, 'phone' => '9000000022'])
            ->assertUnprocessable()->assertJsonMissingPath('session');
        $this->postJson('/api/v1/direct/paid-registrations', [...$body, 'password' => 'Another strong password 2026!'])
            ->assertUnprocessable()->assertJsonMissingPath('session');
        self::assertSame(1, DB::table('subscription_checkouts')->count());
        self::assertFalse(User::query()->where('username', '9000000022')->exists());
    }

    public function test_current_paid_plan_is_rejected_before_new_checkout_but_expired_plan_can_be_bought(): void
    {
        $body = ['phone' => '9000000023', 'password' => 'Correct horse battery 2026!',
            'plan_id' => $this->planId(DirectPlanDefaults::MONTHLY_MODULES),
            'idempotency_key' => (string) Str::ulid()];
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/direct/paid-registrations', $body)->assertCreated();
        $this->postJson('/api/v1/direct/paid-registrations', [...$body, 'idempotency_key' => (string) Str::ulid()])
            ->assertUnprocessable();
        self::assertSame(1, DB::table('subscription_checkouts')->count());
        $this->travel(32)->days();
        $this->postJson('/api/v1/direct/paid-registrations', [...$body, 'idempotency_key' => (string) Str::ulid()])
            ->assertCreated()->assertJsonPath('status', 'completed');
        self::assertSame(2, DB::table('subscription_checkouts')->count());
    }

    private function planId(string $code): string
    {
        $this->getJson('/api/v1/direct/subscription-plans')->assertOk();
        $id = DB::table('entitlement_plans')->where('code', $code)->value('id');
        self::assertIsString($id);

        return $id;
    }
}
