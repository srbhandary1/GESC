<?php

declare(strict_types=1);

namespace App\Entitlements;

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

        return DB::transaction(function () use (

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

                $payload = $this->checkoutPayload($existing);
                $payload['replayed'] = true;

                return $payload;
            }

            $plan = DB::table('entitlement_plans')
                ->where('organization_id', $organizationId)
                ->where('id', $planId)
                ->where('status', 'active')
                ->first();

            if (! $plan instanceof stdClass || (int) $plan->amount_ex_gst_paise <= 0) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
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

            $capture = $this->payments->capture(new CheckoutOrder(
                checkoutId: $checkoutId,
                amountExGstPaise: $payable['amount_ex_gst_paise'],
                gstPaise: $payable['gst_paise'],
                totalPaise: $payable['total_paise'],
                planCode: $this->string($plan, 'code'),
            ));

            if ($capture->status !== 'completed') {
                DB::table('subscription_checkouts')->where('id', $checkoutId)->update([
                    'status' => 'failed',
                    'provider' => $capture->provider,
                    'provider_reference' => $capture->providerReference,
                    'updated_at' => Date::now(),
                ]);
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            if (DirectPlanDefaults::isTalkPack($plan)) {
                return $this->completeTalkPackCheckout(
                    $organizationId,
                    $membershipId,
                    $plan,
                    $checkoutId,
                    $capture->provider,
                    $capture->providerReference,
                );
            }

            $starts = CarbonImmutable::now('Asia/Kolkata');
            $expires = $this->string($plan, 'billing_period') === 'calendar_month'
                ? $starts->addMonths((int) $plan->duration_value)
                : $starts->addHours((int) $plan->duration_value);
            $entitlementId = (string) Str::ulid();
            $this->access->expireActive($organizationId, $membershipId);
            DB::table('learner_entitlements')->insert([
                'id' => $entitlementId,
                'organization_id' => $organizationId,
                'learner_membership_id' => $membershipId,
                'plan_id' => $planId,
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
                'entitlement_id' => $entitlementId,
                'updated_at' => Date::now(),
            ]);

            $completed = DB::table('subscription_checkouts')->where('id', $checkoutId)->first();

            if (! $completed instanceof stdClass) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }

            return $this->checkoutPayload($completed);
        });
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
    ): array {
        $entitlement = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (! $entitlement instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::TALK_PACK_REQUIRES_PAID);
        }

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
            'entitlement_id' => $this->string($entitlement, 'id'),
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
    private function checkoutPayload(stdClass $checkout): array
    {
        return [
            'id' => $this->string($checkout, 'id'),
            'status' => $this->string($checkout, 'status'),
            'plan_id' => $this->string($checkout, 'plan_id'),
            'amount_ex_gst_paise' => (int) $checkout->amount_ex_gst_paise,
            'gst_paise' => (int) $checkout->gst_paise,
            'total_paise' => (int) $checkout->amount_ex_gst_paise + (int) $checkout->gst_paise,
            'gstin_is_placeholder' => (bool) $checkout->gstin_is_placeholder,
            'entitlement_id' => $this->nullableString($checkout, 'entitlement_id'),
            'replayed' => false,
        ];
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
