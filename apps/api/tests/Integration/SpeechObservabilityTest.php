<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationSessionService;
use App\Conversation\ConversationTurnService;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Observability\ProviderAttemptObservationRecorder;
use App\Conversation\Observability\SpeechEconomicsEvidenceRejected;
use App\Conversation\Observability\SpeechLatencyTelemetryService;
use App\Conversation\Observability\SpeechObservabilityRetentionService;
use App\Conversation\Observability\SpeechProviderStatementRecorder;
use App\Conversation\Observability\SpeechPublishedRateCardSeeder;
use App\Conversation\Observability\SpeechRateCardResolver;
use App\Conversation\Providers\ProviderBillableUsage;
use App\Conversation\Providers\ProviderUsage;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Operator\LearnerPrivacyInventory;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SpeechObservabilityTest extends TenancyIntegrationTestCase
{
    public function test_fake_turn_records_normalized_content_free_attempts(): void
    {
        [$learner, $membership, $context] = $this->learner();
        [$session, $turn] = $this->conversation($learner, $context);

        $this->assertSame('completed', $turn['status']);
        $this->assertSame(3, DB::table('ai_cost_ledger')->count());
        $this->assertSame(3, DB::table('speech_provider_attempt_details')->count());
        $this->assertSame(3, DB::table('speech_provider_usage_read_model')->count());
        $this->assertSame(
            ['model', 'stt', 'tts'],
            DB::table('speech_provider_usage_read_model')
                ->orderBy('provider_stage')
                ->pluck('provider_stage')
                ->all(),
        );
        $this->assertSame(
            ['local_execution'],
            DB::table('speech_provider_usage_read_model')
                ->distinct()
                ->pluck('dispatch_kind')
                ->all(),
        );
        $this->assertSame(
            ['friend'],
            DB::table('speech_provider_usage_read_model')
                ->distinct()
                ->pluck('conversation_mode')
                ->all(),
        );
        $this->assertSame(
            ['not_billable'],
            DB::table('speech_provider_usage_read_model')
                ->distinct()
                ->pluck('reconciliation_status')
                ->all(),
        );

        $snapshot = json_encode(
            DB::table('speech_provider_attempt_details')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
        $this->assertStringNotContainsString(
            'I practise English after work.',
            $snapshot,
        );
        $this->assertStringNotContainsString(
            'Thank you for sharing that.',
            $snapshot,
        );
        $privacyMembership = DB::table('memberships')
            ->where('id', $membership->getKey())
            ->first(['id', 'user_id']);
        $this->assertNotNull($privacyMembership);
        $privacy = $this->app->make(LearnerPrivacyInventory::class)->export(
            $context->organizationId,
            $privacyMembership,
        );
        $attemptGroup = self::privacyGroup(
            $privacy,
            'speech_provider_usage_read_model',
        );
        $this->assertCount(3, $attemptGroup['records']);
        $this->assertSame(
            'aggregate_anonymously_then_delete_within_30_days',
            $attemptGroup['erasure_disposition'],
        );

        $this->expectException(QueryException::class);
        DB::table('ai_cost_ledger')
            ->where('conversation_turn_id', $turn['id'])
            ->update(['latency_ms' => 999]);
    }

    public function test_rate_card_quote_freezes_units_currency_and_conversion(): void
    {
        $now = Date::now()->toImmutable();
        $rateCardId = (string) Str::ulid();
        DB::table('speech_rate_cards')->insert([
            'id' => $rateCardId,
            'provider_key' => 'synthetic-speech',
            'provider_stage' => 'tts',
            'model_version' => 'synthetic-v1',
            'api_version' => '2026-09-01',
            'voice_key' => 'synthetic-voice',
            'region' => 'centralindia',
            'configuration_sha256' => str_repeat('a', 64),
            'rate_version' => 'synthetic-2026-09-v1',
            'billable_unit_kind' => 'unicode_characters',
            'unit_basis' => 1000,
            'minimum_billable_units' => 100,
            'tier_start_units' => 0,
            'tier_end_units' => null,
            'billing_currency' => 'USD',
            'gross_price_microunits' => 20_000,
            'tax_basis_points' => 1800,
            'discount_basis_points' => 500,
            'credit_microunits_per_basis' => 1000,
            'inr_per_native_unit_microunits' => 83_000_000,
            'conversion_source' => 'synthetic-test-fixture',
            'conversion_observed_at' => $now,
            'source_evidence_sha256' => str_repeat('b', 64),
            'approval_evidence' => 'APP-TEST-SPEECH-RATE',
            'effective_from' => $now->subDay(),
            'effective_until' => $now->addDay(),
            'recorded_at' => $now,
        ]);

        $usage = new ProviderUsage(
            providerKey: 'synthetic-speech',
            modelVersion: 'synthetic-v1',
            latencyMilliseconds: 200,
            inputUnits: 50,
            outputUnits: 1000,
            estimatedCostMicrounits: 0,
            inputUnitKind: 'unicode_characters',
            outputUnitKind: 'audio_bytes',
            billableUnitKind: 'unicode_characters',
            billableUnitQuantity: 50,
            apiVersion: '2026-09-01',
            voiceKey: 'synthetic-voice',
            region: 'centralindia',
            configurationSha256: str_repeat('a', 64),
        );
        $rates = $this->app->make(SpeechRateCardResolver::class);
        $quote = $rates->quote('tts', $usage, $now);

        $this->assertNotNull($quote);
        $this->assertSame($rateCardId, $quote->rateCardId);
        $this->assertSame('USD', $quote->nativeCurrency);
        $this->assertSame(2160, $quote->nativeCostMicrounits);
        $this->assertSame(179_280, $quote->inrCostMicrounits);
        $this->assertNull($rates->quote('tts', $usage, $now->addDays(2)));
        $this->assertSame(
            'rate_stale',
            $rates->unresolvedStatus('tts', $usage, $now->addDays(2)),
        );

        $this->expectException(QueryException::class);
        DB::table('speech_rate_cards')
            ->where('id', $rateCardId)
            ->update(['gross_price_microunits' => 1]);
    }

    public function test_model_attempt_rates_input_and_output_components_separately(): void
    {
        [$learner, $membership, $context] = $this->learner();
        [$session, $turn] = $this->conversation($learner, $context);
        $now = Date::now()->toImmutable();
        foreach ([
            ['input', 'provider_input_tokens', 400_000],
            ['output', 'provider_output_tokens', 1_600_000],
        ] as [$componentKey, $unitKind, $price]) {
            DB::table('speech_rate_cards')->insert([
                'id' => (string) Str::ulid(),
                'provider_key' => 'deterministic-fake',
                'provider_stage' => 'model',
                'model_version' => 'fake-model-v1',
                'api_version' => null,
                'voice_key' => null,
                'region' => null,
                'configuration_sha256' => null,
                'rate_version' => 'synthetic-model-'.$componentKey.'-v1',
                'billable_unit_kind' => $unitKind,
                'unit_basis' => 1_000_000,
                'minimum_billable_units' => 0,
                'tier_start_units' => 0,
                'tier_end_units' => null,
                'billing_currency' => 'USD',
                'gross_price_microunits' => $price,
                'tax_basis_points' => 0,
                'discount_basis_points' => 0,
                'credit_microunits_per_basis' => 0,
                'inr_per_native_unit_microunits' => 83_000_000,
                'conversion_source' => 'synthetic-test-fixture',
                'conversion_observed_at' => $now,
                'source_evidence_sha256' => str_repeat('c', 64),
                'approval_evidence' => 'APP-TEST-SPEECH-RATE',
                'effective_from' => $now->subDay(),
                'effective_until' => $now->addDay(),
                'recorded_at' => $now,
            ]);
        }

        $ledgerId = (string) Str::ulid();
        DB::table('ai_cost_ledger')->insert([
            'id' => $ledgerId,
            'organization_id' => $context->organizationId,
            'conversation_turn_id' => $turn['id'],
            'conversation_session_id' => $session['id'],
            'learner_membership_id' => $membership->getKey(),
            'provider_stage' => 'model',
            'provider_key' => 'deterministic-fake',
            'model_version' => 'fake-model-v1',
            'provider_attempt' => 99,
            'latency_ms' => 200,
            'input_units' => 1000,
            'output_units' => 500,
            'estimated_cost_microunits' => 0,
            'result_status' => 'succeeded',
            'recorded_at' => $now,
        ]);
        $usage = new ProviderUsage(
            providerKey: 'deterministic-fake',
            modelVersion: 'fake-model-v1',
            latencyMilliseconds: 200,
            inputUnits: 1000,
            outputUnits: 500,
            estimatedCostMicrounits: 0,
            inputUnitKind: 'provider_input_tokens',
            outputUnitKind: 'provider_output_tokens',
            billableComponents: [
                new ProviderBillableUsage(
                    componentKey: 'input',
                    unitKind: 'provider_input_tokens',
                    quantity: 1000,
                ),
                new ProviderBillableUsage(
                    componentKey: 'output',
                    unitKind: 'provider_output_tokens',
                    quantity: 500,
                ),
            ],
        );
        $this->app->make(ProviderAttemptObservationRecorder::class)
            ->recordAiLedger(
                organizationId: $context->organizationId,
                ledgerId: $ledgerId,
                sessionId: (string) $session['id'],
                stage: 'model',
                semanticOperation: 'test.multi_component_model',
                routeRole: 'primary',
                resultStatus: 'succeeded',
                safeFailureCode: null,
                usage: $usage,
                recordedAt: $now,
            );

        $detail = DB::table('speech_provider_attempt_details')
            ->where('ai_cost_ledger_id', $ledgerId)
            ->first();
        $this->assertNotNull($detail);
        $this->assertNull($detail->billable_unit_kind);
        $this->assertSame(1200, (int) $detail->estimated_native_cost_microunits);
        $this->assertSame(99_600, (int) $detail->estimated_inr_cost_microunits);
        $this->assertSame(
            [
                'provider_input_tokens' => 400,
                'provider_output_tokens' => 800,
            ],
            DB::table('speech_provider_attempt_charges')
                ->where('speech_provider_attempt_detail_id', $detail->id)
                ->orderBy('component_key')
                ->pluck(
                    'estimated_native_cost_microunits',
                    'billable_unit_kind',
                )
                ->map(static fn (mixed $value): int => (int) $value)
                ->all(),
        );

        $privacyMembership = DB::table('memberships')
            ->where('id', $membership->getKey())
            ->first(['id', 'user_id']);
        $this->assertNotNull($privacyMembership);
        $privacy = $this->app->make(LearnerPrivacyInventory::class)->export(
            $context->organizationId,
            $privacyMembership,
        );
        $charges = self::privacyGroup(
            $privacy,
            'speech_provider_charge_usage_read_model',
        );
        $this->assertCount(2, $charges['records']);

        DB::table('speech_rate_cards')->insert([
            'id' => (string) Str::ulid(),
            'provider_key' => 'deterministic-fake',
            'provider_stage' => 'model',
            'model_version' => 'fake-model-v1',
            'api_version' => null,
            'voice_key' => null,
            'region' => null,
            'configuration_sha256' => null,
            'rate_version' => 'synthetic-model-input-v2',
            'billable_unit_kind' => 'provider_input_tokens',
            'unit_basis' => 1_000_000,
            'minimum_billable_units' => 0,
            'tier_start_units' => 0,
            'tier_end_units' => null,
            'billing_currency' => 'USD',
            'gross_price_microunits' => 500_000,
            'tax_basis_points' => 0,
            'discount_basis_points' => 0,
            'credit_microunits_per_basis' => 0,
            'inr_per_native_unit_microunits' => 83_000_000,
            'conversion_source' => 'synthetic-test-fixture',
            'conversion_observed_at' => $now,
            'source_evidence_sha256' => str_repeat('d', 64),
            'approval_evidence' => 'APP-TEST-SPEECH-RATE',
            'effective_from' => $now->subDay(),
            'effective_until' => $now->addDay(),
            'recorded_at' => $now,
        ]);
        $inputComponent = $usage->normalizedBillableComponents()[0];
        $rates = $this->app->make(SpeechRateCardResolver::class);
        $this->assertNull($rates->quoteComponent(
            'model',
            $usage,
            $inputComponent,
            $now,
        ));
        $this->assertSame(
            'rate_ambiguous',
            $rates->componentResolutionStatus(
                'model',
                $usage,
                $inputComponent,
                $now,
            ),
        );

        $this->expectException(QueryException::class);
        DB::table('speech_provider_attempt_charges')
            ->where('speech_provider_attempt_detail_id', $detail->id)
            ->update(['billable_unit_quantity' => 1]);
    }

    public function test_normalized_attempts_distinguish_terminal_status_route_and_dispatch(): void
    {
        [$learner, $membership, $context] = $this->learner();
        [$session, $turn] = $this->conversation($learner, $context);
        $now = Date::now()->toImmutable();
        $recorder = $this->app->make(ProviderAttemptObservationRecorder::class);
        $cases = [
            ['failed', 'primary', 'provider_dispatch', 'provider_timeout'],
            ['cancelled', 'primary', 'provider_dispatch', 'client_cancelled'],
            ['partial', 'fallback', 'provider_dispatch', 'stream_interrupted'],
            ['succeeded', 'primary', 'cache', null],
        ];

        foreach ($cases as $offset => [$status, $route, $dispatch, $failure]) {
            $ledgerId = (string) Str::ulid();
            DB::table('ai_cost_ledger')->insert([
                'id' => $ledgerId,
                'organization_id' => $context->organizationId,
                'conversation_turn_id' => $turn['id'],
                'conversation_session_id' => $session['id'],
                'learner_membership_id' => $membership->getKey(),
                'provider_stage' => 'stt',
                'provider_key' => 'deterministic-fake',
                'model_version' => 'fake-stt-v1',
                'provider_attempt' => 10 + $offset,
                'latency_ms' => 100 + $offset,
                'input_units' => 1000,
                'output_units' => 20,
                'estimated_cost_microunits' => 0,
                'result_status' => $status === 'succeeded'
                    ? 'succeeded'
                    : 'failed',
                'recorded_at' => $now,
            ]);
            $recorder->recordAiLedger(
                organizationId: $context->organizationId,
                ledgerId: $ledgerId,
                sessionId: (string) $session['id'],
                stage: 'stt',
                semanticOperation: 'test.normalized_attempt',
                routeRole: $route,
                resultStatus: $status,
                safeFailureCode: $failure,
                usage: new ProviderUsage(
                    providerKey: 'deterministic-fake',
                    modelVersion: 'fake-stt-v1',
                    latencyMilliseconds: 100 + $offset,
                    inputUnits: 1000,
                    outputUnits: 20,
                    estimatedCostMicrounits: 0,
                    inputUnitKind: 'audio_milliseconds',
                    outputUnitKind: 'unicode_characters',
                    billableUnitKind: 'audio_milliseconds',
                    billableUnitQuantity: 1000,
                    dispatchKind: $dispatch,
                ),
                recordedAt: $now,
            );
        }

        $rows = DB::table('speech_provider_usage_read_model')
            ->where('semantic_operation', 'test.normalized_attempt')
            ->orderBy('source_id')
            ->get();
        $this->assertCount(4, $rows);
        $this->assertEqualsCanonicalizing(
            ['failed', 'cancelled', 'partial', 'succeeded'],
            $rows->pluck('result_status')->all(),
        );
        $this->assertSame(1, $rows->where('route_role', 'fallback')->count());
        $this->assertSame(1, $rows->where('dispatch_kind', 'cache')->count());
        $this->assertSame(4, $rows->pluck('source_id')->unique()->count());
        $this->assertSame(
            ['not_billable', 'rate_missing'],
            $rows->pluck('reconciliation_status')->unique()->sort()->values()->all(),
        );
    }

    public function test_statement_reconciliation_is_content_free_idempotent_and_variance_aware(): void
    {
        $now = Date::now()->toImmutable();
        $recorder = $this->app->make(SpeechProviderStatementRecorder::class);
        $lines = [[
            'provider_stage' => 'tts',
            'model_version' => 'synthetic-v1',
            'billable_unit_kind' => 'unicode_characters',
            'observed_billable_quantity' => 50_000,
            'estimated_inr_cost_microunits' => 90_000,
            'allocated_statement_inr_microunits' => 91_300,
            'evidence_sha256' => str_repeat('d', 64),
            'approval_evidence' => 'APP-TEST-STATEMENT-LINE',
        ]];
        $arguments = [
            'providerKey' => 'synthetic-speech',
            'statementReferenceSha256' => str_repeat('a', 64),
            'periodStart' => $now->startOfMonth(),
            'periodEnd' => $now->endOfMonth(),
            'billingCurrency' => 'USD',
            'grossCostMicrounits' => 1000,
            'taxMicrounits' => 180,
            'discountMicrounits' => 50,
            'creditMicrounits' => 30,
            'netCostMicrounits' => 1100,
            'inrPerNativeUnitMicrounits' => 83_000_000,
            'conversionSource' => 'synthetic-test-fixture',
            'conversionObservedAt' => $now,
            'evidenceSha256' => str_repeat('b', 64),
            'approvalEvidence' => 'APP-TEST-STATEMENT',
            'lines' => $lines,
        ];

        $created = $recorder->record(...$arguments);
        $replay = $recorder->record(...$arguments);

        $this->assertFalse($created['replayed']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($created['statement_id'], $replay['statement_id']);
        $this->assertDatabaseHas('speech_provider_statements', [
            'id' => $created['statement_id'],
            'net_cost_microunits' => 1100,
            'net_cost_inr_microunits' => 91_300,
        ]);
        $this->assertDatabaseHas('speech_statement_reconciliations', [
            'speech_provider_statement_id' => $created['statement_id'],
            'variance_inr_microunits' => 1300,
            'variance_basis_points' => 145,
            'status' => 'reconciled',
        ]);

        $this->expectException(SpeechEconomicsEvidenceRejected::class);
        $recorder->record(...[
            ...$arguments,
            'lines' => [[
                ...$lines[0],
                'allocated_statement_inr_microunits' => 100_000,
            ]],
        ]);
    }

    public function test_latency_is_tenant_scoped_idempotent_and_aggregated_before_expiry(): void
    {
        config()->set('speech_observability.client_sample_rate_bps', 10_000);
        config()->set('speech_observability.small_cell_minimum', 2);
        $old = Date::now()->toImmutable()->subDays(31);
        Date::setTestNow($old);
        [$learner, $membership, $context] = $this->learner();
        [$session, $turn] = $this->conversation($learner, $context);
        $service = $this->app->make(SpeechLatencyTelemetryService::class);
        $command = (string) Str::uuid();
        $payload = [
            'client_runtime' => 'android',
            'network_class' => 'cellular',
            'playback_outcome' => 'started',
            'release_to_ack_ms' => 40,
            'release_to_first_signal_ms' => 1900,
            'release_to_first_audio_ms' => 2500,
            'turn_json_to_first_audio_ms' => 600,
        ];

        $recorded = $this->contexts()->run(
            $context,
            fn (): array => $service->record(
                $learner,
                (string) $turn['id'],
                $command,
                $payload,
            ),
        );
        $replay = $this->contexts()->run(
            $context,
            fn (): array => $service->record(
                $learner,
                (string) $turn['id'],
                $command,
                $payload,
            ),
        );

        $this->assertTrue($recorded['accepted']);
        $this->assertFalse($recorded['replayed']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame(1, DB::table('speech_client_latency_samples')->count());
        $privacyMembership = DB::table('memberships')
            ->where('id', $membership->getKey())
            ->first(['id', 'user_id']);
        $this->assertNotNull($privacyMembership);
        $privacy = $this->app->make(LearnerPrivacyInventory::class)->export(
            $context->organizationId,
            $privacyMembership,
        );
        $latencyGroup = self::privacyGroup(
            $privacy,
            'speech_client_latency_samples',
        );
        $this->assertCount(1, $latencyGroup['records']);
        $this->assertSame(
            'aggregate_anonymously_then_delete_within_30_days',
            $latencyGroup['erasure_disposition'],
        );

        $ledgerId = (string) Str::ulid();
        DB::table('ai_cost_ledger')->insert([
            'id' => $ledgerId,
            'organization_id' => $context->organizationId,
            'conversation_turn_id' => $turn['id'],
            'conversation_session_id' => $session['id'],
            'learner_membership_id' => $membership->getKey(),
            'provider_stage' => 'stt',
            'provider_key' => 'deterministic-fake',
            'model_version' => 'fake-stt-v1',
            'provider_attempt' => 88,
            'latency_ms' => 120,
            'input_units' => 1000,
            'output_units' => 20,
            'estimated_cost_microunits' => 0,
            'result_status' => 'succeeded',
            'recorded_at' => $old,
        ]);
        $this->app->make(ProviderAttemptObservationRecorder::class)
            ->recordAiLedger(
                organizationId: $context->organizationId,
                ledgerId: $ledgerId,
                sessionId: (string) $session['id'],
                stage: 'stt',
                semanticOperation: 'test.retained_charge',
                routeRole: 'primary',
                resultStatus: 'succeeded',
                safeFailureCode: null,
                usage: new ProviderUsage(
                    providerKey: 'deterministic-fake',
                    modelVersion: 'fake-stt-v1',
                    latencyMilliseconds: 120,
                    inputUnits: 1000,
                    outputUnits: 20,
                    estimatedCostMicrounits: 0,
                    inputUnitKind: 'audio_milliseconds',
                    outputUnitKind: 'unicode_characters',
                    billableUnitKind: 'audio_milliseconds',
                    billableUnitQuantity: 1000,
                ),
                recordedAt: $old,
            );
        $this->assertSame(1, DB::table('speech_provider_attempt_charges')->count());

        [$otherLearner, , $otherContext] = $this->learner(
            $this->createOrganization('Other Speech Tenant'),
        );
        try {
            $this->contexts()->run(
                $otherContext,
                fn (): array => $service->record(
                    $otherLearner,
                    (string) $turn['id'],
                    (string) Str::uuid(),
                    $payload,
                ),
            );
            self::fail('Expected cross-tenant telemetry write to fail closed.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }

        Date::setTestNow($old->addDays(31)->addMinute());
        $purged = $this->app
            ->make(SpeechObservabilityRetentionService::class)
            ->purgeDue();

        $this->assertSame(4, $purged['provider_attempts_aggregated']);
        $this->assertSame(1, $purged['charge_components_aggregated']);
        $this->assertSame(1, $purged['client_samples_aggregated']);
        $this->assertSame(5, $purged['source_rows_deleted']);
        $this->assertSame(0, DB::table('ai_cost_ledger')->count());
        $this->assertSame(0, DB::table('speech_provider_attempt_charges')->count());
        $this->assertSame(0, DB::table('speech_client_latency_samples')->count());
        $this->assertSame(6, DB::table('speech_usage_daily_aggregates')->count());

        $chargeAggregate = DB::table('speech_usage_daily_aggregates')
            ->where('aggregate_kind', 'provider_charge')
            ->first();
        $this->assertNotNull($chargeAggregate);
        $this->assertSame(0, (int) $chargeAggregate->attempt_count);
        $this->assertSame(1, (int) $chargeAggregate->source_sample_count);
        $this->assertSame(1000, (int) $chargeAggregate->billable_unit_quantity);

        $clientAggregate = DB::table('speech_usage_daily_aggregates')
            ->where('aggregate_kind', 'client_latency')
            ->first();
        $this->assertNotNull($clientAggregate);
        $this->assertTrue((bool) $clientAggregate->small_cell_suppressed);
        $this->assertSame(10_000, (int) $clientAggregate->sample_rate_bps);
        $this->assertSame('speech-first-audible-v1', $clientAggregate->model_version);
        $this->assertSame(1, (int) $clientAggregate->timing_sample_count);
        $histogram = json_decode(
            (string) $clientAggregate->first_audible_latency_histogram,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertSame(1, $histogram['le_4000']);
    }

    public function test_synthetic_rate_card_and_statement_reconcile_without_content(): void
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
        $this->assertSame(
            0,
            $this->app->make(SpeechPublishedRateCardSeeder::class)->seed(),
        );

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
        $this->assertSame(
            ['reconciled'],
            DB::table('speech_statement_reconciliations')->pluck('status')->all(),
        );
        $snapshot = json_encode(
            DB::table('speech_provider_statements')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
        $this->assertStringNotContainsString('transcript', $snapshot);
        $this->assertStringNotContainsString('audio', $snapshot);
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(?Organization $organization = null): array
    {
        $organization ??= Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole(
            $context,
            'speech-observability-'.strtolower((string) Str::ulid()),
        );
        $permission = Permission::query()
            ->where('key', 'learning.participate')
            ->firstOrFail();
        $now = Date::now();
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learner_profiles')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $membership->getKey(),
            'adult_confirmed_at' => $now,
            'onboarding_completed_at' => $now,
            'created_at' => $now,
        ]);

        if ($organization->system_key === Organization::DIRECT_SYSTEM_KEY) {
            $this->contexts()->run($context, function () use ($user, $organization): void {
                $this->app->make(EntitlementAccess::class)->snapshot($user);
                $planId = DB::table('entitlement_plans')
                    ->where('organization_id', $organization->getKey())
                    ->where('code', DirectPlanDefaults::MONTHLY_MODULES)
                    ->value('id');
                $this->assertIsString($planId);
                $this->app->make(LearnerSubscriptionService::class)->checkout(
                    $user,
                    $planId,
                    (string) Str::ulid(),
                );
            });
        }

        return [$user, $membership, $context];
    }

    /**
     * @param  array<string, mixed>  $privacy
     * @return array<string, mixed>
     */
    private static function privacyGroup(
        array $privacy,
        string $recordClass,
    ): array {
        $groups = $privacy['record_groups'] ?? null;
        self::assertIsArray($groups);

        foreach ($groups as $group) {
            if (
                is_array($group)
                && ($group['record_class'] ?? null) === $recordClass
            ) {
                return $group;
            }
        }

        self::fail('Expected privacy record group '.$recordClass.'.');
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    private function conversation(
        User $learner,
        OrganizationContext $context,
    ): array {
        try {
            $session = $this->contexts()->run(
                $context,
                fn (): array => $this->app->make(ConversationSessionService::class)
                    ->start(
                        learner: $learner,
                        personaKey: 'patient-friend-prototype',
                        scenarioKey: 'everyday-friend-prototype',
                        coachingMode: 'gentle',
                        uiLocale: 'en-IN',
                        idempotencyKey: (string) Str::uuid(),
                    ),
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                'Conversation session fixture failed: '.$exception::class.' '.$exception->getMessage(),
                previous: $exception,
            );
        }

        try {
            $turn = $this->contexts()->run(
                $context,
                fn (): array => $this->app->make(ConversationTurnService::class)
                    ->create(
                        learner: $learner,
                        sessionId: (string) $session['id'],
                        prototypeUtterance: 'I practise English after work.',
                        idempotencyKey: (string) Str::uuid(),
                    ),
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                'Conversation turn fixture failed: '.$exception::class.' '.$exception->getMessage(),
                previous: $exception,
            );
        }

        return [$session, $turn];
    }
}
