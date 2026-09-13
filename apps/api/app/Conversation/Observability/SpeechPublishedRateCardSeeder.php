<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SpeechPublishedRateCardSeeder
{
    public function seed(): int
    {
        $cards = config('speech_observability.published_rate_cards', []);
        if (! is_array($cards)) {
            return 0;
        }

        $now = Date::now()->toImmutable();
        $written = 0;
        foreach ($cards as $card) {
            if (! is_array($card) || ! $this->valid($card)) {
                throw new SpeechEconomicsEvidenceRejected;
            }

            $exists = DB::table('speech_rate_cards')
                ->where('provider_key', $card['provider_key'])
                ->where('provider_stage', $card['provider_stage'])
                ->where('rate_version', $card['rate_version'])
                ->where('billable_unit_kind', $card['billable_unit_kind'])
                ->where('tier_start_units', 0)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('speech_rate_cards')->insert([
                'id' => (string) Str::ulid(),
                'provider_key' => $card['provider_key'],
                'provider_stage' => $card['provider_stage'],
                'model_version' => $card['model_version'],
                'api_version' => $card['api_version'] ?? null,
                'voice_key' => $card['voice_key'] ?? null,
                'region' => $card['region'] ?? null,
                'configuration_sha256' => $card['configuration_sha256'] ?? null,
                'rate_version' => $card['rate_version'],
                'billable_unit_kind' => $card['billable_unit_kind'],
                'unit_basis' => $card['unit_basis'],
                'minimum_billable_units' => $card['minimum_billable_units'] ?? 0,
                'tier_start_units' => 0,
                'tier_end_units' => null,
                'billing_currency' => $card['billing_currency'],
                'gross_price_microunits' => $card['gross_price_microunits'],
                'tax_basis_points' => $card['tax_basis_points'] ?? 0,
                'discount_basis_points' => $card['discount_basis_points'] ?? 0,
                'credit_microunits_per_basis' => $card['credit_microunits_per_basis'] ?? 0,
                'inr_per_native_unit_microunits' => $card['inr_per_native_unit_microunits'],
                'conversion_source' => $card['conversion_source'],
                'conversion_observed_at' => $now,
                'source_evidence_sha256' => $card['source_evidence_sha256'],
                'approval_evidence' => $card['approval_evidence'],
                'effective_from' => $now,
                'effective_until' => null,
                'recorded_at' => $now,
            ]);
            $written++;
        }

        return $written;
    }

    /** @param array<string, mixed> $card */
    private function valid(array $card): bool
    {
        return is_string($card['provider_key'] ?? null)
            && is_string($card['provider_stage'] ?? null)
            && is_string($card['model_version'] ?? null)
            && is_string($card['rate_version'] ?? null)
            && is_string($card['billable_unit_kind'] ?? null)
            && is_int($card['unit_basis'] ?? null)
            && $card['unit_basis'] > 0
            && is_string($card['billing_currency'] ?? null)
            && is_int($card['gross_price_microunits'] ?? null)
            && is_int($card['inr_per_native_unit_microunits'] ?? null)
            && is_string($card['conversion_source'] ?? null)
            && is_string($card['source_evidence_sha256'] ?? null)
            && preg_match('/^[0-9a-f]{64}$/', $card['source_evidence_sha256']) === 1
            && is_string($card['approval_evidence'] ?? null);
    }
}
