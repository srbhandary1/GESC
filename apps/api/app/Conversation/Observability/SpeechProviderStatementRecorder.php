<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class SpeechProviderStatementRecorder
{
    /**
     * @param  list<array{
     *   provider_stage: string,
     *   model_version: string,
     *   billable_unit_kind: string,
     *   observed_billable_quantity: int,
     *   estimated_inr_cost_microunits: int,
     *   allocated_statement_inr_microunits: int,
     *   evidence_sha256: string,
     *   approval_evidence: string
     * }>  $lines
     * @return array{statement_id: string, replayed: bool, reconciliations: int}
     */
    public function record(
        string $providerKey,
        string $statementReferenceSha256,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        string $billingCurrency,
        int $grossCostMicrounits,
        int $taxMicrounits,
        int $discountMicrounits,
        int $creditMicrounits,
        int $netCostMicrounits,
        int $inrPerNativeUnitMicrounits,
        string $conversionSource,
        DateTimeInterface $conversionObservedAt,
        string $evidenceSha256,
        string $approvalEvidence,
        array $lines,
    ): array {
        $this->assertStatement(
            providerKey: $providerKey,
            statementReferenceSha256: $statementReferenceSha256,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            billingCurrency: $billingCurrency,
            grossCostMicrounits: $grossCostMicrounits,
            taxMicrounits: $taxMicrounits,
            discountMicrounits: $discountMicrounits,
            creditMicrounits: $creditMicrounits,
            netCostMicrounits: $netCostMicrounits,
            inrPerNativeUnitMicrounits: $inrPerNativeUnitMicrounits,
            conversionSource: $conversionSource,
            evidenceSha256: $evidenceSha256,
            approvalEvidence: $approvalEvidence,
            lines: $lines,
        );
        $netInr = $this->ceilRatio(
            $netCostMicrounits * $inrPerNativeUnitMicrounits,
            1_000_000,
        );
        $payloadHash = $this->payloadHash([
            $providerKey,
            $statementReferenceSha256,
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
            $billingCurrency,
            $grossCostMicrounits,
            $taxMicrounits,
            $discountMicrounits,
            $creditMicrounits,
            $netCostMicrounits,
            $inrPerNativeUnitMicrounits,
            $conversionSource,
            $conversionObservedAt->format('U.u'),
            $evidenceSha256,
            $approvalEvidence,
            $lines,
        ]);

        return DB::transaction(function () use (
            $providerKey,
            $statementReferenceSha256,
            $periodStart,
            $periodEnd,
            $billingCurrency,
            $grossCostMicrounits,
            $taxMicrounits,
            $discountMicrounits,
            $creditMicrounits,
            $netCostMicrounits,
            $inrPerNativeUnitMicrounits,
            $conversionSource,
            $conversionObservedAt,
            $netInr,
            $evidenceSha256,
            $approvalEvidence,
            $lines,
            $payloadHash,
        ): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['speech-statement|'.$providerKey.'|'.$statementReferenceSha256],
                );
            }

            $existing = DB::table('speech_provider_statements')
                ->where('provider_key', $providerKey)
                ->where('statement_reference_sha256', $statementReferenceSha256)
                ->first();

            if ($existing instanceof stdClass) {
                $this->assertReplay(
                    statement: $existing,
                    billingCurrency: $billingCurrency,
                    grossCostMicrounits: $grossCostMicrounits,
                    taxMicrounits: $taxMicrounits,
                    discountMicrounits: $discountMicrounits,
                    creditMicrounits: $creditMicrounits,
                    netCostMicrounits: $netCostMicrounits,
                    inrPerNativeUnitMicrounits: $inrPerNativeUnitMicrounits,
                    netInr: $netInr,
                    evidenceSha256: $evidenceSha256,
                    approvalEvidence: $approvalEvidence,
                    lines: $lines,
                    payloadHash: $payloadHash,
                );

                return [
                    'statement_id' => (string) $existing->id,
                    'replayed' => true,
                    'reconciliations' => count($lines),
                ];
            }

            $statementId = (string) Str::ulid();
            $recordedAt = Date::now()->toImmutable();
            DB::table('speech_provider_statements')->insert([
                'id' => $statementId,
                'provider_key' => $providerKey,
                'statement_reference_sha256' => $statementReferenceSha256,
                'payload_sha256' => $payloadHash,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'billing_currency' => $billingCurrency,
                'gross_cost_microunits' => $grossCostMicrounits,
                'tax_microunits' => $taxMicrounits,
                'discount_microunits' => $discountMicrounits,
                'credit_microunits' => $creditMicrounits,
                'net_cost_microunits' => $netCostMicrounits,
                'inr_per_native_unit_microunits' => $inrPerNativeUnitMicrounits,
                'conversion_source' => $conversionSource,
                'conversion_observed_at' => $conversionObservedAt,
                'net_cost_inr_microunits' => $netInr,
                'evidence_sha256' => $evidenceSha256,
                'approval_evidence' => $approvalEvidence,
                'recorded_at' => $recordedAt,
            ]);

            foreach ($lines as $line) {
                $variance = $line['allocated_statement_inr_microunits']
                    - $line['estimated_inr_cost_microunits'];
                $varianceBps = $line['estimated_inr_cost_microunits'] > 0
                    ? $this->ceilRatio(
                        abs($variance) * 10_000,
                        $line['estimated_inr_cost_microunits'],
                    )
                    : null;
                $withinTolerance = $variance === 0
                    || (
                        $varianceBps !== null
                        && $varianceBps <= (int) config(
                            'speech_observability.invoice_variance_tolerance_bps',
                            500,
                        )
                    );
                DB::table('speech_statement_reconciliations')->insert([
                    'id' => (string) Str::ulid(),
                    'speech_provider_statement_id' => $statementId,
                    'provider_stage' => $line['provider_stage'],
                    'model_version' => $line['model_version'],
                    'billable_unit_kind' => $line['billable_unit_kind'],
                    'observed_billable_quantity' => $line['observed_billable_quantity'],
                    'estimated_inr_cost_microunits' => $line['estimated_inr_cost_microunits'],
                    'allocated_statement_inr_microunits' => $line['allocated_statement_inr_microunits'],
                    'variance_inr_microunits' => $variance,
                    'variance_basis_points' => $varianceBps,
                    'status' => $withinTolerance
                        ? 'reconciled'
                        : 'variance_exceeds_tolerance',
                    'evidence_sha256' => $line['evidence_sha256'],
                    'approval_evidence' => $line['approval_evidence'],
                    'recorded_at' => $recordedAt,
                ]);
            }

            return [
                'statement_id' => $statementId,
                'replayed' => false,
                'reconciliations' => count($lines),
            ];
        }, 3);
    }

    /** @param list<array<string, mixed>> $lines */
    private function assertStatement(
        string $providerKey,
        string $statementReferenceSha256,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        string $billingCurrency,
        int $grossCostMicrounits,
        int $taxMicrounits,
        int $discountMicrounits,
        int $creditMicrounits,
        int $netCostMicrounits,
        int $inrPerNativeUnitMicrounits,
        string $conversionSource,
        string $evidenceSha256,
        string $approvalEvidence,
        array $lines,
    ): void {
        if (
            preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $providerKey) !== 1
            || preg_match('/^[0-9a-f]{64}$/', $statementReferenceSha256) !== 1
            || $periodEnd < $periodStart
            || preg_match('/^[A-Z]{3}$/', $billingCurrency) !== 1
            || min(
                $grossCostMicrounits,
                $taxMicrounits,
                $discountMicrounits,
                $creditMicrounits,
                $netCostMicrounits,
            ) < 0
            || $netCostMicrounits !== $grossCostMicrounits
                + $taxMicrounits
                - $discountMicrounits
                - $creditMicrounits
            || $inrPerNativeUnitMicrounits <= 0
            || trim($conversionSource) === ''
            || mb_strlen($conversionSource) > 160
            || preg_match('/^[0-9a-f]{64}$/', $evidenceSha256) !== 1
            || trim($approvalEvidence) === ''
            || mb_strlen($approvalEvidence) > 64
            || $lines === []
        ) {
            throw new SpeechEconomicsEvidenceRejected;
        }

        $allocatedInrMicrounits = 0;
        foreach ($lines as $line) {
            if (
                ! in_array($line['provider_stage'] ?? null, ['stt', 'model', 'tts'], true)
                || ! is_string($line['model_version'] ?? null)
                || trim($line['model_version']) === ''
                || mb_strlen($line['model_version']) > 100
                || ! is_string($line['billable_unit_kind'] ?? null)
                || preg_match('/^[a-z][a-z0-9._-]{0,39}$/', $line['billable_unit_kind']) !== 1
                || ! is_int($line['observed_billable_quantity'] ?? null)
                || $line['observed_billable_quantity'] < 0
                || ! is_int($line['estimated_inr_cost_microunits'] ?? null)
                || $line['estimated_inr_cost_microunits'] < 0
                || ! is_int($line['allocated_statement_inr_microunits'] ?? null)
                || $line['allocated_statement_inr_microunits'] < 0
                || ! is_string($line['evidence_sha256'] ?? null)
                || preg_match('/^[0-9a-f]{64}$/', $line['evidence_sha256']) !== 1
                || ! is_string($line['approval_evidence'] ?? null)
                || trim($line['approval_evidence']) === ''
                || mb_strlen($line['approval_evidence']) > 64
            ) {
                throw new SpeechEconomicsEvidenceRejected;
            }
            $allocatedInrMicrounits += $line['allocated_statement_inr_microunits'];
        }

        if (
            $allocatedInrMicrounits !== $this->ceilRatio(
                $netCostMicrounits * $inrPerNativeUnitMicrounits,
                1_000_000,
            )
        ) {
            throw new SpeechEconomicsEvidenceRejected;
        }
    }

    /** @param list<array<string, mixed>> $lines */
    private function assertReplay(
        stdClass $statement,
        string $billingCurrency,
        int $grossCostMicrounits,
        int $taxMicrounits,
        int $discountMicrounits,
        int $creditMicrounits,
        int $netCostMicrounits,
        int $inrPerNativeUnitMicrounits,
        int $netInr,
        string $evidenceSha256,
        string $approvalEvidence,
        array $lines,
        string $payloadHash,
    ): void {
        $storedLines = DB::table('speech_statement_reconciliations')
            ->where('speech_provider_statement_id', (string) $statement->id)
            ->count();
        if (
            (string) $statement->billing_currency !== $billingCurrency
            || (int) $statement->gross_cost_microunits !== $grossCostMicrounits
            || (int) $statement->tax_microunits !== $taxMicrounits
            || (int) $statement->discount_microunits !== $discountMicrounits
            || (int) $statement->credit_microunits !== $creditMicrounits
            || (int) $statement->net_cost_microunits !== $netCostMicrounits
            || (int) $statement->inr_per_native_unit_microunits !== $inrPerNativeUnitMicrounits
            || (int) $statement->net_cost_inr_microunits !== $netInr
            || (string) $statement->evidence_sha256 !== $evidenceSha256
            || (string) $statement->approval_evidence !== $approvalEvidence
            || $storedLines !== count($lines)
            || (string) $statement->payload_sha256 !== $payloadHash
        ) {
            throw new SpeechEconomicsEvidenceRejected;
        }
    }

    /** @param array<int, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function ceilRatio(int $numerator, int $denominator): int
    {
        if ($numerator <= 0) {
            return 0;
        }

        return intdiv($numerator + $denominator - 1, $denominator);
    }
}
