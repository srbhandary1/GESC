<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;

final class GuidanceTtsCostLedgerConstraintTest extends TenancyIntegrationTestCase
{
    public function test_soft_launch_guidance_tts_usage_is_allowlisted(): void
    {
        $definition = (string) DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE conname = 'ai_cost_ledger_values_check'",
        )->def;

        self::assertStringContainsString('azure-speech-guidance', $definition);
        self::assertStringContainsString('soft-launch-guidance-tts-v1:', $definition);
    }
}
