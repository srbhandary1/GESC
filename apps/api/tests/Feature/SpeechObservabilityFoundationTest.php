<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Observability\SpeechProviderStatementRecorder;
use App\Conversation\Observability\SpeechPublishedRateCardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SpeechObservabilityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_rate_card_seeds_once_and_statement_is_content_free(): void
    {
        config()->set('speech_observability.published_rate_cards', [[
            'provider_key' => 'synthetic-speech',
            'provider_stage' => 'stt',
            'model_version' => 'synthetic-stt-v1',
            'rate_version' => 'synthetic-test-v1',
            'billable_unit_kind' => 'audio_milliseconds',
            'unit_basis' => 3_600_000,
            'billing_currency' => 'USD',
            'gross_price_microunits' => 1_000_000,
            'inr_per_native_unit_microunits' => 83_000_000,
            'conversion_source' => 'synthetic-test-fixture',
            'source_evidence_sha256' => str_repeat('c', 64),
            'approval_evidence' => 'APP-TEST-SPEECH-RATE',
        ]]);
        $written = $this->app->make(SpeechPublishedRateCardSeeder::class)->seed();
        $this->assertSame(1, $written);
        $this->assertSame(1, DB::table('speech_rate_cards')->count());
        $this->assertSame(0, $this->app->make(SpeechPublishedRateCardSeeder::class)->seed());

        $now = Date::now()->toImmutable();
        $result = $this->app->make(SpeechProviderStatementRecorder::class)->record(
            providerKey: 'synthetic-speech',
            statementReferenceSha256: str_repeat('d', 64),
            periodStart: $now->subDay(),
            periodEnd: $now,
            billingCurrency: 'USD',
            grossCostMicrounits: 1_000_000,
            taxMicrounits: 180_000,
            discountMicrounits: 0,
            creditMicrounits: 0,
            netCostMicrounits: 1_180_000,
            inrPerNativeUnitMicrounits: 83_000_000,
            conversionSource: 'synthetic-test-fixture',
            conversionObservedAt: $now,
            evidenceSha256: str_repeat('e', 64),
            approvalEvidence: 'D-0307',
            lines: [[
                'provider_stage' => 'stt',
                'model_version' => 'synthetic-stt-v1',
                'billable_unit_kind' => 'audio_milliseconds',
                'observed_billable_quantity' => 25_000,
                'estimated_inr_cost_microunits' => 97_940_000,
                'allocated_statement_inr_microunits' => 97_940_000,
                'evidence_sha256' => str_repeat('f', 64),
                'approval_evidence' => 'D-0307',
            ]],
        );

        $this->assertFalse($result['replayed']);
        $this->assertSame(1, $result['reconciliations']);
        $snapshot = json_encode(
            DB::table('speech_provider_statements')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
        $this->assertStringNotContainsString('transcript', $snapshot);
    }
}
