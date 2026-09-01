<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;

final class IndicSttCostLedgerConstraintTest extends TenancyIntegrationTestCase
{
    public function test_indic_and_azure_recorded_stt_usage_are_allowlisted(): void
    {
        $definition = (string) DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE conname = 'ai_cost_ledger_values_check'",
        )->def;

        self::assertStringContainsString('indic-http-stt', $definition);
        self::assertStringContainsString('indic-http-stt-v1:', $definition);
        self::assertStringContainsString('azure-speech-soft-launch-stt', $definition);
        self::assertStringContainsString(
            'fast-transcription-2025-10-15-provider-managed',
            $definition,
        );
    }
}
