<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DirectCatalogSeeder
{
    public function seed(string $organizationId, string $now): void
    {
        $existingTax = DB::table('organization_tax_settings')
            ->where('organization_id', $organizationId)
            ->exists();

        if (! $existingTax) {
            DB::table('organization_tax_settings')->insert([
                'organization_id' => $organizationId,
                'gst_registered' => true,
                'gstin' => DirectPlanDefaults::PLACEHOLDER_GSTIN,
                'gst_rate_bps' => DirectPlanDefaults::PLACEHOLDER_GST_RATE_BPS,
                'gstin_is_placeholder' => true,
                'version' => 1,
                'updated_by_membership_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DirectPlanDefaults::plans() as $plan) {
            $existing = DB::table('entitlement_plans')
                ->where('organization_id', $organizationId)
                ->where('code', $plan['code'])
                ->first();

            if ($existing instanceof stdClass) {
                $updates = [];

                if ((int) $existing->version === 1 && $this->string($existing, 'name') !== $plan['name']) {
                    $updates['name'] = $plan['name'];
                }

                if (
                    $existing->live_ai_cap_seconds === null
                    && $plan['live_ai_cap_seconds'] !== null
                    && in_array($plan['code'], [
                        DirectPlanDefaults::MONTHLY_MODULES,
                        DirectPlanDefaults::MONTHLY_ROLEPLAY,
                    ], true)
                ) {
                    $updates['live_ai_cap_seconds'] = $plan['live_ai_cap_seconds'];
                }

                if ($updates !== []) {
                    $updates['updated_at'] = $now;
                    DB::table('entitlement_plans')
                        ->where('id', $this->string($existing, 'id'))
                        ->update($updates);
                }

                continue;
            }

            DB::table('entitlement_plans')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'code' => $plan['code'],
                'name' => $plan['name'],
                'amount_ex_gst_paise' => $plan['amount_ex_gst_paise'],
                'billing_period' => $plan['billing_period'],
                'duration_value' => $plan['duration_value'],
                'allows_roleplay' => $plan['allows_roleplay'],
                'max_level_number' => $plan['max_level_number'],
                'live_ai_cap_seconds' => $plan['live_ai_cap_seconds'],
                'is_demo' => $plan['is_demo'],
                'status' => 'active',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function seedDirectIfPresent(): void
    {
        $organizationId = DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->value('id');

        if (! is_string($organizationId) || $organizationId === '') {
            return;
        }

        $this->seed($organizationId, now()->toIso8601String());
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : '';
    }
}
