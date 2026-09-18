<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Billing\CheckoutCapture;
use App\Billing\CheckoutOrder;
use App\Billing\PaymentProvider;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

final readonly class LearnerSubscriptionService
{
    public function __construct(
        private LearnerAuthority $authority,
        private EntitlementAccess $access,
        private DirectCatalogSeeder $catalog,
        private PaymentProvider $payments,
    ) {}

    /**
     * @return array{
     *   gstin_is_placeholder: bool,
     *   gst_registered: bool,
     *   plans: list<array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     amount_ex_gst_paise: int,
     *     gst_paise: int,
     *     total_paise: int,
     *     gst_rate_bps: int,
     *     billing_period: string,
     *     allows_roleplay: bool,
     *     max_level_number: int|null,
     *     live_ai_cap_seconds: int|null,
     *     is_demo: bool,
     *     is_talk_pack: bool,
     *     checkout_required: bool,
     *     recommended: bool
     *   }>
     * }
     */
    public function catalogue(User $learner): array
    {
        $context = $this->authority->require($learner);
        if (! $this->access->applies($context->organizationId)) {
            return [
                'gstin_is_placeholder' => false,
                'gst_registered' => false,
                'plans' => [],
            ];
        }
        $this->catalog->seed($context->organizationId, Date::now()->toIso8601String());
        $tax = $this->tax($context->organizationId);
        $plans = DB::table('entitlement_plans')
            ->where('organization_id', $context->organizationId)
            ->where('status', 'active')
            ->orderByRaw("CASE WHEN billing_period = 'talk_pack' THEN 1 ELSE 0 END")
            ->orderByRaw("CASE WHEN billing_period = 'talk_pack' THEN live_ai_cap_seconds ELSE 0 END")
            ->orderByDesc('amount_ex_gst_paise')
            ->orderBy('code')
            ->get();
        $listed = [];

        foreach ($plans as $plan) {
            $payable = GstCalculator::payable(
                (int) $plan->amount_ex_gst_paise,
                (int) $tax->gst_rate_bps,
                (bool) $tax->gst_registered,
            );
            $code = $this->string($plan, 'code');
            $isTalkPack = DirectPlanDefaults::isTalkPack($plan);
            $listed[] = [
                'id' => $this->string($plan, 'id'),
                'code' => $code,
                'name' => $this->string($plan, 'name'),
                'amount_ex_gst_paise' => (int) $plan->amount_ex_gst_paise,
                'gst_paise' => $payable['gst_paise'],
                'total_paise' => $payable['total_paise'],
                'gst_rate_bps' => $payable['gst_rate_bps'],
                'billing_period' => $this->string($plan, 'billing_period'),
                'allows_roleplay' => (bool) $plan->allows_roleplay,
                'max_level_number' => $this->nullableInt($plan, 'max_level_number'),
                'live_ai_cap_seconds' => $this->nullableInt($plan, 'live_ai_cap_seconds'),
                'is_demo' => (bool) $plan->is_demo,
                'is_talk_pack' => $isTalkPack,
                'checkout_required' => (int) $plan->amount_ex_gst_paise > 0,
                'recommended' => $code === DirectPlanDefaults::MONTHLY_ROLEPLAY,
            ];
        }

        return [
            'gstin_is_placeholder' => (bool) $tax->gstin_is_placeholder,
            'gst_registered' => (bool) $tax->gst_registered,
            'plans' => $listed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout(User $learner, string $planId, string $idempotencyKey): array
    {
        $context = $this->authority->require($learner);
        $organizationId = $context->organizationId;
        $membershipId = (string) $context->membershipId;

        if (! $this->access->applies($organizationId)) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        $prepared = DB::transaction(function () use (
            $planId,
            $idempotencyKey,
            $organizationId,
            $membershipId,
        ): array {
            $this->catalog->seed($organizationId, Date::now()->toIso8601String());

            $existing = DB::table('subscription_checkouts')
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof stdClass) {
                if ($this->string($existing, 'plan_id') !== $planId) {
                    throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
                }

                return ['created' => false, 'checkout' => $existing, 'order' => null];
            }

            $plan = DB::table('entitlement_plans')
                ->where('organization_id', $organizationId)
                ->where('id', $planId)
                ->where('status', 'active')
                ->first();

            if (! $plan instanceof stdClass || (int) $plan->amount_ex_gst_paise <= 0) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            if (DirectPlanDefaults::isTalkPack($plan)) {
                $this->requirePaidEntitlement($organizationId, $membershipId, false);
            }

            $tax = $this->tax($organizationId);
            $payable = GstCalculator::payable(
                (int) $plan->amount_ex_gst_paise,
                (int) $tax->gst_rate_bps,
                (bool) $tax->gst_registered,
            );
            $checkoutId = (string) Str::ulid();
            $now = Date::now();
            DB::table('subscription_checkouts')->insert([
                'id' => $checkoutId,
                'organization_id' => $organizationId,
                'learner_membership_id' => $membershipId,
                'plan_id' => $planId,
                'plan_snapshot' => json_encode(
                    $this->access->planSnapshot($plan),
                    JSON_THROW_ON_ERROR,
                ),
                'status' => 'pending',
                'amount_ex_gst_paise' => $payable['amount_ex_gst_paise'],
                'gst_paise' => $payable['gst_paise'],
                'gst_rate_bps' => $payable['gst_rate_bps'],
                'gstin' => $this->string($tax, 'gstin'),
                'gstin_is_placeholder' => (bool) $tax->gstin_is_placeholder,
                'provider' => 'pending',
                'provider_reference' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'entitlement_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $checkout = DB::table('subscription_checkouts')->where('id', $checkoutId)->first();
            if (! $checkout instanceof stdClass) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            return ['created' => true, 'checkout' => $checkout, 'order' => new CheckoutOrder(
                checkoutId: $checkoutId,
                amountExGstPaise: $payable['amount_ex_gst_paise'],
                gstPaise: $payable['gst_paise'],
                totalPaise: $payable['total_paise'],
                planCode: $this->string($plan, 'code'),
            )];
        });

        /** @var stdClass $checkout */
        $checkout = $prepared['checkout'];
        if ($prepared['created'] !== true) {
            $payload = $this->checkoutPayload($checkout);
            $payload['replayed'] = true;

            return $payload;
        }

        try {
            /** @var CheckoutOrder $order */
            $order = $prepared['order'];
            $capture = $this->payments->capture($order);
        } catch (EntitlementDenied $exception) {
            DB::table('subscription_checkouts')
                ->where('id', $this->string($checkout, 'id'))
                ->update([
                    'status' => 'failed',
                    'failed_at' => Date::now(),
                    'updated_at' => Date::now(),
                ]);
            throw $exception;
        } catch (Throwable $exception) {
            // The provider may have received the request even when its response was lost.
            // Keep the durable pending record so replay cannot create a second order.
            report($exception);

            return $this->checkoutPayload($checkout);
        }

        return $this->applyCapture($this->string($checkout, 'id'), $capture);
    }

    /** @return array<string, mixed> */
    public function confirmCheckout(
        User $learner,
        string $checkoutId,
        string $providerPaymentReference,
        string $signature,
    ): array {
        $context = $this->authority->require($learner);
        if (! $this->access->applies($context->organizationId)) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        $checkout = $this->authorizedCheckout(
            $checkoutId,
            $context->organizationId,
            (string) $context->membershipId,
        );
        if ($this->string($checkout, 'status') === 'completed') {
            $payload = $this->checkoutPayload($checkout);
            $payload['replayed'] = true;

            return $payload;
        }

        $order = $this->checkoutOrder($checkout);
        $capture = $this->payments->confirm(
            $order,
            $this->string($checkout, 'provider_reference'),
            $providerPaymentReference,
            $signature,
        );

        return $this->applyCapture($checkoutId, $capture);
    }

    /** @return array<string, mixed> */
    public function reconcileCheckout(User $learner, string $checkoutId): array
    {
        $context = $this->authority->require($learner);
        if (! $this->access->applies($context->organizationId)) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        $checkout = $this->authorizedCheckout(
            $checkoutId,
            $context->organizationId,
            (string) $context->membershipId,
        );
        if ($this->string($checkout, 'status') !== 'pending') {
            return $this->checkoutPayload($checkout);
        }

        if (
            $this->string($checkout, 'provider') !== 'razorpay'
            || ! str_starts_with($this->string($checkout, 'provider_reference'), 'order_')
        ) {
            return $this->checkoutPayload($checkout);
        }

        $capture = $this->payments->reconcile(
            $this->checkoutOrder($checkout),
            $this->string($checkout, 'provider_reference'),
            $this->nullableString($checkout, 'provider_payment_reference'),
        );

        return $this->applyCapture($checkoutId, $capture);
    }

    public function reconcileProviderCheckout(
        string $providerReference,
        ?string $providerPaymentReference,
    ): void {
        $checkout = DB::table('subscription_checkouts')
            ->where('provider', 'razorpay')
            ->where('provider_reference', $providerReference)
            ->first();
        if (! $checkout instanceof stdClass || $this->string($checkout, 'status') !== 'pending') {
            return;
        }

        $capture = $this->payments->reconcile(
            $this->checkoutOrder($checkout),
            $providerReference,
            $providerPaymentReference,
        );
        $this->applyCapture($this->string($checkout, 'id'), $capture);
    }

    /** @return array<string, mixed> */
    private function applyCapture(string $checkoutId, CheckoutCapture $capture): array
    {
        return DB::transaction(function () use ($checkoutId, $capture): array {
            $checkout = DB::table('subscription_checkouts')
                ->where('id', $checkoutId)
                ->lockForUpdate()
                ->first();
            if (! $checkout instanceof stdClass) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            if ($this->string($checkout, 'status') === 'completed') {
                $payload = $this->checkoutPayload($checkout, $capture);
                $payload['replayed'] = true;

                return $payload;
            }

            if ($this->string($checkout, 'status') !== 'pending') {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            if ($capture->status !== 'completed') {
                DB::table('subscription_checkouts')->where('id', $checkoutId)->update([
                    'provider' => $capture->provider,
                    'provider_reference' => $capture->providerReference,
                    'provider_payment_reference' => $capture->providerPaymentReference,
                    'updated_at' => Date::now(),
                ]);
                $pending = DB::table('subscription_checkouts')->where('id', $checkoutId)->first();
                if (! $pending instanceof stdClass) {
                    throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
                }

                return $this->checkoutPayload($pending, $capture);
            }

            $plan = $this->checkoutPlan($checkout);
            if (! $plan instanceof stdClass) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            if (DirectPlanDefaults::isTalkPack($plan)) {
                return $this->completeTalkPackCheckout(
                    $this->string($checkout, 'organization_id'),
                    $this->string($checkout, 'learner_membership_id'),
                    $plan,
                    $checkoutId,
                    $capture->provider,
                    $capture->providerReference,
                    $capture->providerPaymentReference,
                );
            }

            $starts = CarbonImmutable::now('Asia/Kolkata');
            $expires = $this->string($plan, 'billing_period') === 'calendar_month'
                ? $starts->addMonths((int) $plan->duration_value)
                : $starts->addHours((int) $plan->duration_value);
            $entitlementId = (string) Str::ulid();
            $organizationId = $this->string($checkout, 'organization_id');
            $membershipId = $this->string($checkout, 'learner_membership_id');
            $this->access->expireActive($organizationId, $membershipId);
            DB::table('learner_entitlements')->insert([
                'id' => $entitlementId,
                'organization_id' => $organizationId,
                'learner_membership_id' => $membershipId,
                'plan_id' => $this->string($checkout, 'plan_id'),
                'plan_snapshot' => json_encode($this->access->planSnapshot($plan), JSON_THROW_ON_ERROR),
                'status' => 'active',
                'source' => 'checkout',
                'starts_at' => $starts,
                'first_sign_in_at' => $starts,
                'expires_at' => $expires,
                'live_ai_seconds_used' => 0,
                'created_at' => $starts,
                'updated_at' => $starts,
            ]);
            $this->access->event($organizationId, $entitlementId, 'granted', $membershipId, [
                'source' => 'checkout',
                'plan_code' => $this->string($plan, 'code'),
                'checkout_id' => $checkoutId,
            ]);
            DB::table('subscription_checkouts')->where('id', $checkoutId)->update([
                'status' => 'completed',
                'provider' => $capture->provider,
                'provider_reference' => $capture->providerReference,
                'provider_payment_reference' => $capture->providerPaymentReference,
                'entitlement_id' => $entitlementId,
                'completed_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);

            $completed = DB::table('subscription_checkouts')->where('id', $checkoutId)->first();
            if (! $completed instanceof stdClass) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            return $this->checkoutPayload($completed, $capture);
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function completeTalkPackCheckout(
        string $organizationId,
        string $membershipId,
        stdClass $plan,
        string $checkoutId,
        string $provider,
        string $providerReference,
        ?string $providerPaymentReference = null,
    ): array {
        $entitlement = $this->requirePaidEntitlement($organizationId, $membershipId, true);

        $now = CarbonImmutable::now('Asia/Kolkata');
        $this->access->addTalkPack(
            $organizationId,
            $entitlement,
            $plan,
            $checkoutId,
            $now,
        );
        DB::table('subscription_checkouts')->where('id', $checkoutId)->update([
            'status' => 'completed',
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'provider_payment_reference' => $providerPaymentReference,
            'entitlement_id' => $this->string($entitlement, 'id'),
            'completed_at' => Date::now(),
            'updated_at' => Date::now(),
        ]);
        $completed = DB::table('subscription_checkouts')->where('id', $checkoutId)->first();

        if (! $completed instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        return $this->checkoutPayload($completed);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(
        stdClass $checkout,
        ?CheckoutCapture $capture = null,
    ): array {
        return [
            'id' => $this->string($checkout, 'id'),
            'status' => $this->string($checkout, 'status'),
            'plan_id' => $this->string($checkout, 'plan_id'),
            'amount_ex_gst_paise' => (int) $checkout->amount_ex_gst_paise,
            'gst_paise' => (int) $checkout->gst_paise,
            'total_paise' => (int) $checkout->amount_ex_gst_paise + (int) $checkout->gst_paise,
            'gstin_is_placeholder' => (bool) $checkout->gstin_is_placeholder,
            'entitlement_id' => $this->nullableString($checkout, 'entitlement_id'),
            'provider' => $this->string($checkout, 'provider'),
            'provider_order_id' => $this->string($checkout, 'provider') === 'razorpay'
                ? $this->string($checkout, 'provider_reference')
                : null,
            'provider_payment_id' => $this->nullableString($checkout, 'provider_payment_reference'),
            'provider_public_key' => $capture !== null
                ? $capture->publicKey
                : ($this->string($checkout, 'provider') === 'razorpay'
                    ? $this->payments->publicKey()
                    : null),
            'currency' => 'INR',
            'replayed' => false,
        ];
    }

    private function authorizedCheckout(
        string $checkoutId,
        string $organizationId,
        string $membershipId,
    ): stdClass {
        $checkout = DB::table('subscription_checkouts')
            ->where('id', $checkoutId)
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->first();
        if (! $checkout instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        return $checkout;
    }

    private function checkoutOrder(stdClass $checkout): CheckoutOrder
    {
        $plan = $this->checkoutPlan($checkout);
        if (! $plan instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        return new CheckoutOrder(
            checkoutId: $this->string($checkout, 'id'),
            amountExGstPaise: (int) $checkout->amount_ex_gst_paise,
            gstPaise: (int) $checkout->gst_paise,
            totalPaise: (int) $checkout->amount_ex_gst_paise + (int) $checkout->gst_paise,
            planCode: $this->string($plan, 'code'),
        );
    }

    private function checkoutPlan(stdClass $checkout): ?stdClass
    {
        $encoded = $checkout->plan_snapshot ?? null;
        if (is_string($encoded) && $encoded !== '') {
            $decoded = json_decode($encoded, false, 32, JSON_THROW_ON_ERROR);
            if ($decoded instanceof stdClass) {
                return $decoded;
            }
        }

        return DB::table('entitlement_plans')
            ->where('organization_id', $this->string($checkout, 'organization_id'))
            ->where('id', $this->string($checkout, 'plan_id'))
            ->first();
    }

    private function requirePaidEntitlement(
        string $organizationId,
        string $membershipId,
        bool $lock,
    ): stdClass {
        $query = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active');
        if ($lock) {
            $query->lockForUpdate();
        }
        $entitlement = $query->first();
        if (! $entitlement instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::TALK_PACK_REQUIRES_PAID);
        }

        $snapshot = json_decode((string) $entitlement->plan_snapshot, true);
        if (! is_array($snapshot) || ($snapshot['is_demo'] ?? true) !== false) {
            throw new EntitlementDenied(EntitlementDenied::TALK_PACK_REQUIRES_PAID);
        }

        return $entitlement;
    }

    private function tax(string $organizationId): stdClass
    {
        $tax = DB::table('organization_tax_settings')
            ->where('organization_id', $organizationId)
            ->first();

        if (! $tax instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        return $tax;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : '';
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableInt(stdClass $row, string $field): ?int
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
