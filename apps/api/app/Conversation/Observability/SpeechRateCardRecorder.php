<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class SpeechRateCardRecorder
{
    /**
     * @param  array<string, int|string|null>  $card
     * @return array{rate_card_id: string, replayed: bool}
     */
    public function record(array $card, DateTimeInterface $conversionObservedAt, DateTimeInterface $effectiveFrom, ?DateTimeInterface $effectiveUntil): array
    {
        $this->assertCard($card, $effectiveFrom, $effectiveUntil);

        return DB::transaction(function () use ($card, $conversionObservedAt, $effectiveFrom, $effectiveUntil): array {
            $identity = [
                'provider_key' => $card['provider_key'],
                'provider_stage' => $card['provider_stage'],
                'rate_version' => $card['rate_version'],
                'billable_unit_kind' => $card['billable_unit_kind'],
                'tier_start_units' => $card['tier_start_units'],
            ];
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                    'speech-rate-card|'.implode('|', array_map('strval', $identity)),
                ]);
            }

            $existing = DB::table('speech_rate_cards')->where($identity)->first();
            if ($existing instanceof stdClass) {
                $this->assertReplay($existing, $card, $conversionObservedAt, $effectiveFrom, $effectiveUntil);

                return ['rate_card_id' => (string) $existing->id, 'replayed' => true];
            }

            $id = (string) Str::ulid();
            DB::table('speech_rate_cards')->insert([
                'id' => $id,
                ...$card,
                'conversion_observed_at' => $conversionObservedAt,
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
                'recorded_at' => Date::now()->toImmutable(),
            ]);

            return ['rate_card_id' => $id, 'replayed' => false];
        }, 3);
    }

    /** @param array<string, int|string|null> $card */
    private function assertCard(array $card, DateTimeInterface $effectiveFrom, ?DateTimeInterface $effectiveUntil): void
    {
        $requiredStrings = ['provider_key', 'provider_stage', 'model_version', 'rate_version', 'billable_unit_kind', 'billing_currency', 'conversion_source', 'source_evidence_sha256', 'approval_evidence'];
        foreach ($requiredStrings as $key) {
            if (! is_string($card[$key] ?? null) || trim((string) $card[$key]) === '') {
                throw new SpeechEconomicsEvidenceRejected;
            }
        }
        foreach (['unit_basis', 'minimum_billable_units', 'tier_start_units', 'gross_price_microunits', 'tax_basis_points', 'discount_basis_points', 'credit_microunits_per_basis', 'inr_per_native_unit_microunits'] as $key) {
            if (! is_int($card[$key] ?? null) || (int) $card[$key] < 0) {
                throw new SpeechEconomicsEvidenceRejected;
            }
        }
        if (! in_array($card['provider_stage'], ['stt', 'model', 'tts'], true)
            || (int) $card['unit_basis'] < 1
            || (int) $card['inr_per_native_unit_microunits'] < 1
            || (int) $card['tax_basis_points'] > 10_000
            || (int) $card['discount_basis_points'] > 10_000
            || preg_match('/^[A-Z]{3}$/', (string) $card['billing_currency']) !== 1
            || preg_match('/^[0-9a-f]{64}$/', (string) $card['source_evidence_sha256']) !== 1
            || ($card['configuration_sha256'] !== null && preg_match('/^[0-9a-f]{64}$/', (string) $card['configuration_sha256']) !== 1)
            || ($card['tier_end_units'] !== null && (int) $card['tier_end_units'] <= (int) $card['tier_start_units'])
            || ($effectiveUntil !== null && $effectiveUntil <= $effectiveFrom)
        ) {
            throw new SpeechEconomicsEvidenceRejected;
        }
    }

    /** @param array<string, int|string|null> $card */
    private function assertReplay(stdClass $existing, array $card, DateTimeInterface $conversionObservedAt, DateTimeInterface $effectiveFrom, ?DateTimeInterface $effectiveUntil): void
    {
        foreach ($card as $key => $value) {
            $stored = $existing->{$key};
            if (($value === null && $stored !== null) || ($value !== null && (string) $stored !== (string) $value)) {
                throw new SpeechEconomicsEvidenceRejected;
            }
        }
        foreach (['conversion_observed_at' => $conversionObservedAt, 'effective_from' => $effectiveFrom, 'effective_until' => $effectiveUntil] as $key => $value) {
            if (($value === null && $existing->{$key} !== null) || ($value !== null && strtotime((string) $existing->{$key}) !== $value->getTimestamp())) {
                throw new SpeechEconomicsEvidenceRejected;
            }
        }
    }
}
