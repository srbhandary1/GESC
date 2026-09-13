<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speech_rate_cards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider_key', 64);
            $table->string('provider_stage', 16);
            $table->string('model_version', 100);
            $table->string('api_version', 64)->nullable();
            $table->string('voice_key', 100)->nullable();
            $table->string('region', 64)->nullable();
            $table->char('configuration_sha256', 64)->nullable();
            $table->string('rate_version', 80);
            $table->string('billable_unit_kind', 40);
            $table->unsignedBigInteger('unit_basis');
            $table->unsignedBigInteger('minimum_billable_units')->default(0);
            $table->unsignedBigInteger('tier_start_units')->default(0);
            $table->unsignedBigInteger('tier_end_units')->nullable();
            $table->char('billing_currency', 3);
            $table->unsignedBigInteger('gross_price_microunits');
            $table->unsignedSmallInteger('tax_basis_points')->default(0);
            $table->unsignedSmallInteger('discount_basis_points')->default(0);
            $table->unsignedBigInteger('credit_microunits_per_basis')->default(0);
            $table->unsignedBigInteger('inr_per_native_unit_microunits');
            $table->string('conversion_source', 160);
            $table->timestampTz('conversion_observed_at');
            $table->char('source_evidence_sha256', 64);
            $table->string('approval_evidence', 64);
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_until')->nullable();
            $table->timestampTz('recorded_at');

            $table->unique(
                [
                    'provider_key',
                    'provider_stage',
                    'rate_version',
                    'billable_unit_kind',
                    'tier_start_units',
                ],
                'speech_rate_cards_version_unique',
            );
            $table->index(
                [
                    'provider_key',
                    'provider_stage',
                    'model_version',
                    'billable_unit_kind',
                    'effective_from',
                ],
                'speech_rate_cards_resolution_index',
            );
        });

        Schema::create('speech_provider_attempt_details', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('ai_cost_ledger_id')->nullable();
            $table->ulid('voice_provider_run_id')->nullable();
            $table->string('environment', 32);
            $table->string('conversation_mode', 24);
            $table->string('semantic_operation', 64);
            $table->string('route_role', 16);
            $table->string('dispatch_kind', 24);
            $table->string('provider_stage', 16);
            $table->string('provider_key', 64);
            $table->string('model_version', 100);
            $table->string('api_version', 64)->nullable();
            $table->string('voice_key', 100)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('region', 64)->nullable();
            $table->char('configuration_sha256', 64)->nullable();
            $table->string('result_status', 24);
            $table->string('safe_failure_code', 64)->nullable();
            $table->unsignedInteger('connection_latency_ms')->nullable();
            $table->unsignedInteger('first_result_latency_ms')->nullable();
            $table->unsignedInteger('completion_latency_ms');
            $table->string('input_unit_kind', 40);
            $table->unsignedBigInteger('input_unit_quantity');
            $table->string('output_unit_kind', 40);
            $table->unsignedBigInteger('output_unit_quantity');
            $table->string('billable_unit_kind', 40)->nullable();
            $table->unsignedBigInteger('billable_unit_quantity')->nullable();
            $table->foreignUlid('speech_rate_card_id')
                ->nullable()
                ->constrained('speech_rate_cards')
                ->restrictOnDelete();
            $table->char('native_currency', 3)->nullable();
            $table->unsignedBigInteger('estimated_native_cost_microunits')->nullable();
            $table->unsignedBigInteger('estimated_inr_cost_microunits')->nullable();
            $table->string('reconciliation_status', 32);
            $table->timestampTz('recorded_at');
            $table->timestampTz('expires_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'ai_cost_ledger_id'],
                'speech_attempt_details_ai_unique',
            );
            $table->unique(
                ['organization_id', 'voice_provider_run_id'],
                'speech_attempt_details_voice_unique',
            );
            $table->index(
                ['organization_id', 'recorded_at', 'provider_stage'],
                'speech_attempt_details_retention_index',
            );
            $table->foreign(
                ['organization_id', 'ai_cost_ledger_id'],
                'speech_attempt_details_ai_fk',
            )->references(['organization_id', 'id'])
                ->on('ai_cost_ledger')
                ->cascadeOnDelete();
            $table->foreign(
                ['organization_id', 'voice_provider_run_id'],
                'speech_attempt_details_voice_fk',
            )->references(['organization_id', 'id'])
                ->on('voice_provider_runs')
                ->cascadeOnDelete();
        });

        Schema::create('speech_provider_attempt_charges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('speech_provider_attempt_detail_id');
            $table->string('component_key', 32);
            $table->string('billable_unit_kind', 40);
            $table->unsignedBigInteger('billable_unit_quantity');
            $table->foreignUlid('speech_rate_card_id')
                ->nullable()
                ->constrained('speech_rate_cards')
                ->restrictOnDelete();
            $table->char('native_currency', 3)->nullable();
            $table->unsignedBigInteger('estimated_native_cost_microunits')->nullable();
            $table->unsignedBigInteger('estimated_inr_cost_microunits')->nullable();
            $table->string('reconciliation_status', 32);
            $table->timestampTz('recorded_at');
            $table->timestampTz('expires_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'speech_provider_attempt_detail_id',
                    'component_key',
                ],
                'speech_attempt_charges_component_unique',
            );
            $table->index(
                ['organization_id', 'expires_at'],
                'speech_attempt_charges_retention_index',
            );
            $table->foreign(
                ['organization_id', 'speech_provider_attempt_detail_id'],
                'speech_attempt_charges_detail_fk',
            )->references(['organization_id', 'id'])
                ->on('speech_provider_attempt_details')
                ->cascadeOnDelete();
        });

        Schema::create('speech_client_latency_samples', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_turn_id');
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->string('idempotency_key', 128);
            $table->char('payload_sha256', 64);
            $table->string('sample_policy_version', 64);
            $table->unsignedSmallInteger('sample_rate_bps');
            $table->string('environment', 32);
            $table->string('conversation_mode', 24);
            $table->string('client_runtime', 16);
            $table->string('network_class', 16);
            $table->string('playback_outcome', 16);
            $table->unsignedInteger('release_to_ack_ms')->nullable();
            $table->unsignedInteger('release_to_first_signal_ms')->nullable();
            $table->unsignedInteger('release_to_first_audio_ms')->nullable();
            $table->unsignedInteger('turn_json_to_first_audio_ms')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampTz('expires_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'idempotency_key'],
                'speech_client_latency_idempotency_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'conversation_turn_id',
                    'sample_policy_version',
                ],
                'speech_client_latency_turn_policy_unique',
            );
            $table->index(
                ['organization_id', 'recorded_at'],
                'speech_client_latency_retention_index',
            );
            $table->foreign(
                [
                    'organization_id',
                    'conversation_turn_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'speech_client_latency_turn_fk',
            )->references([
                'organization_id',
                'id',
                'conversation_session_id',
                'learner_membership_id',
            ])->on('conversation_turns')
                ->restrictOnDelete();
        });

        Schema::create('speech_usage_daily_aggregates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->date('usage_date');
            $table->string('environment', 32);
            $table->string('aggregate_kind', 24);
            $table->string('provider_stage', 16);
            $table->string('provider_key', 64);
            $table->string('model_version', 100);
            $table->string('route_role', 16);
            $table->string('locale', 16);
            $table->string('conversation_mode', 24);
            $table->string('result_status', 24);
            $table->string('dispatch_kind', 24);
            $table->string('billable_unit_kind', 40);
            $table->string('native_currency', 8);
            $table->string('reconciliation_status', 32);
            $table->unsignedSmallInteger('sample_rate_bps')->default(0);
            $table->unsignedBigInteger('attempt_count')->default(0);
            $table->unsignedBigInteger('dispatch_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('failure_count')->default(0);
            $table->unsignedBigInteger('cancelled_count')->default(0);
            $table->unsignedBigInteger('partial_count')->default(0);
            $table->unsignedBigInteger('fallback_count')->default(0);
            $table->unsignedBigInteger('billable_unit_quantity')->default(0);
            $table->unsignedBigInteger('estimated_native_cost_microunits')->default(0);
            $table->unsignedBigInteger('estimated_inr_cost_microunits')->default(0);
            $table->json('first_result_latency_histogram');
            $table->json('completion_latency_histogram');
            $table->json('first_audible_latency_histogram');
            $table->unsignedBigInteger('timing_sample_count')->default(0);
            $table->unsignedBigInteger('source_sample_count')->default(0);
            $table->boolean('small_cell_suppressed')->default(true);
            $table->timestampTz('first_recorded_at');
            $table->timestampTz('last_recorded_at');
            $table->timestampsTz();

            $table->unique(
                [
                    'organization_id',
                    'usage_date',
                    'environment',
                    'aggregate_kind',
                    'provider_stage',
                    'provider_key',
                    'model_version',
                    'route_role',
                    'locale',
                    'conversation_mode',
                    'result_status',
                    'dispatch_kind',
                    'billable_unit_kind',
                    'native_currency',
                    'reconciliation_status',
                    'sample_rate_bps',
                ],
                'speech_usage_daily_dimension_unique',
            );
        });

        Schema::create('speech_provider_statements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider_key', 64);
            $table->char('statement_reference_sha256', 64);
            $table->char('payload_sha256', 64);
            $table->date('period_start');
            $table->date('period_end');
            $table->char('billing_currency', 3);
            $table->unsignedBigInteger('gross_cost_microunits');
            $table->unsignedBigInteger('tax_microunits')->default(0);
            $table->unsignedBigInteger('discount_microunits')->default(0);
            $table->unsignedBigInteger('credit_microunits')->default(0);
            $table->unsignedBigInteger('net_cost_microunits');
            $table->unsignedBigInteger('inr_per_native_unit_microunits');
            $table->string('conversion_source', 160);
            $table->timestampTz('conversion_observed_at');
            $table->unsignedBigInteger('net_cost_inr_microunits');
            $table->char('evidence_sha256', 64);
            $table->string('approval_evidence', 64);
            $table->timestampTz('recorded_at');

            $table->unique(
                ['provider_key', 'statement_reference_sha256'],
                'speech_provider_statements_reference_unique',
            );
        });

        Schema::create('speech_statement_reconciliations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('speech_provider_statement_id')
                ->constrained('speech_provider_statements')
                ->restrictOnDelete();
            $table->string('provider_stage', 16);
            $table->string('model_version', 100);
            $table->string('billable_unit_kind', 40);
            $table->unsignedBigInteger('observed_billable_quantity');
            $table->unsignedBigInteger('estimated_inr_cost_microunits');
            $table->unsignedBigInteger('allocated_statement_inr_microunits');
            $table->bigInteger('variance_inr_microunits');
            $table->unsignedInteger('variance_basis_points')->nullable();
            $table->string('status', 32);
            $table->char('evidence_sha256', 64);
            $table->string('approval_evidence', 64);
            $table->timestampTz('recorded_at');

            $table->unique(
                [
                    'speech_provider_statement_id',
                    'provider_stage',
                    'model_version',
                    'billable_unit_kind',
                ],
                'speech_statement_reconciliation_dimension_unique',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->addPostgresConstraints();
            $this->addPostgresAppendOnlyTriggers();
        }

        $this->createReadModel();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS speech_provider_charge_usage_read_model');
        DB::statement('DROP VIEW IF EXISTS speech_provider_usage_read_model');

        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach ([
                'ai_cost_ledger',
                'voice_provider_runs',
                'speech_rate_cards',
                'speech_provider_attempt_details',
                'speech_provider_attempt_charges',
                'speech_client_latency_samples',
                'speech_provider_statements',
                'speech_statement_reconciliations',
            ] as $table) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_speech_append_only ON {$table}");
            }
            DB::statement(<<<'SQL'
CREATE TRIGGER ai_cost_ledger_immutable
BEFORE UPDATE OR DELETE ON ai_cost_ledger
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation()
SQL);
            DB::statement('DROP FUNCTION IF EXISTS protect_speech_observability_append_only()');
        }

        Schema::dropIfExists('speech_statement_reconciliations');
        Schema::dropIfExists('speech_provider_statements');
        Schema::dropIfExists('speech_usage_daily_aggregates');
        Schema::dropIfExists('speech_client_latency_samples');
        Schema::dropIfExists('speech_provider_attempt_charges');
        Schema::dropIfExists('speech_provider_attempt_details');
        Schema::dropIfExists('speech_rate_cards');
    }

    private function createReadModel(): void
    {
        $expiresAt = DB::connection()->getDriverName() === 'pgsql'
            ? "ledger.recorded_at + INTERVAL '30 days'"
            : "datetime(ledger.recorded_at, '+30 days')";
        $runExpiresAt = DB::connection()->getDriverName() === 'pgsql'
            ? "runs.recorded_at + INTERVAL '30 days'"
            : "datetime(runs.recorded_at, '+30 days')";

        DB::statement(<<<SQL
CREATE VIEW speech_provider_usage_read_model AS
SELECT
    'ai_cost_ledger' AS source_ledger,
    ledger.id AS source_id,
    details.id AS speech_provider_attempt_detail_id,
    ledger.organization_id,
    ledger.conversation_turn_id,
    NULL AS voice_recording_id,
    ledger.conversation_session_id,
    ledger.learner_membership_id,
    ledger.provider_stage,
    ledger.provider_key,
    ledger.model_version,
    ledger.provider_attempt,
    COALESCE(details.result_status, ledger.result_status) AS result_status,
    details.safe_failure_code,
    COALESCE(details.environment, 'legacy') AS environment,
    COALESCE(details.conversation_mode, 'unknown') AS conversation_mode,
    COALESCE(details.semantic_operation, 'legacy_conversation_' || ledger.provider_stage) AS semantic_operation,
    COALESCE(details.route_role, 'primary') AS route_role,
    COALESCE(details.dispatch_kind, 'unknown') AS dispatch_kind,
    details.api_version,
    details.voice_key,
    details.locale,
    details.region,
    details.configuration_sha256,
    details.connection_latency_ms,
    details.first_result_latency_ms,
    COALESCE(details.completion_latency_ms, ledger.latency_ms) AS completion_latency_ms,
    COALESCE(details.input_unit_kind, 'unknown') AS input_unit_kind,
    ledger.input_units AS input_unit_quantity,
    COALESCE(details.output_unit_kind, 'unknown') AS output_unit_kind,
    ledger.output_units AS output_unit_quantity,
    details.billable_unit_kind,
    details.billable_unit_quantity,
    details.speech_rate_card_id,
    rate_card.rate_version,
    rate_card.unit_basis AS rate_unit_basis,
    rate_card.minimum_billable_units AS rate_minimum_billable_units,
    rate_card.tier_start_units AS rate_tier_start_units,
    rate_card.tier_end_units AS rate_tier_end_units,
    rate_card.gross_price_microunits AS rate_gross_price_microunits,
    rate_card.tax_basis_points AS rate_tax_basis_points,
    rate_card.discount_basis_points AS rate_discount_basis_points,
    rate_card.credit_microunits_per_basis AS rate_credit_microunits_per_basis,
    rate_card.inr_per_native_unit_microunits AS rate_inr_per_native_unit_microunits,
    rate_card.conversion_source AS rate_conversion_source,
    rate_card.conversion_observed_at AS rate_conversion_observed_at,
    details.native_currency,
    details.estimated_native_cost_microunits,
    CASE
        WHEN details.id IS NULL THEN ledger.estimated_cost_microunits
        ELSE details.estimated_inr_cost_microunits
    END AS estimated_inr_cost_microunits,
    COALESCE(details.reconciliation_status, 'legacy_unrated') AS reconciliation_status,
    ledger.recorded_at,
    COALESCE(details.expires_at, {$expiresAt}) AS expires_at
FROM ai_cost_ledger ledger
LEFT JOIN speech_provider_attempt_details details
    ON details.organization_id = ledger.organization_id
    AND details.ai_cost_ledger_id = ledger.id
LEFT JOIN speech_rate_cards rate_card
    ON rate_card.id = details.speech_rate_card_id
UNION ALL
SELECT
    'voice_provider_runs' AS source_ledger,
    runs.id AS source_id,
    details.id AS speech_provider_attempt_detail_id,
    runs.organization_id,
    NULL AS conversation_turn_id,
    runs.voice_recording_id,
    runs.conversation_session_id,
    runs.learner_membership_id,
    runs.provider_stage,
    runs.provider_key,
    runs.model_version,
    runs.provider_attempt,
    COALESCE(details.result_status, runs.result_status) AS result_status,
    COALESCE(details.safe_failure_code, runs.safe_failure_code) AS safe_failure_code,
    COALESCE(details.environment, 'legacy') AS environment,
    COALESCE(details.conversation_mode, 'unknown') AS conversation_mode,
    COALESCE(details.semantic_operation, 'legacy_recorded_' || runs.provider_stage) AS semantic_operation,
    COALESCE(details.route_role, 'primary') AS route_role,
    COALESCE(details.dispatch_kind, 'unknown') AS dispatch_kind,
    details.api_version,
    details.voice_key,
    details.locale,
    details.region,
    details.configuration_sha256,
    details.connection_latency_ms,
    details.first_result_latency_ms,
    COALESCE(details.completion_latency_ms, runs.latency_ms) AS completion_latency_ms,
    COALESCE(details.input_unit_kind, 'unknown') AS input_unit_kind,
    runs.input_units AS input_unit_quantity,
    COALESCE(details.output_unit_kind, 'unknown') AS output_unit_kind,
    runs.output_units AS output_unit_quantity,
    details.billable_unit_kind,
    details.billable_unit_quantity,
    details.speech_rate_card_id,
    rate_card.rate_version,
    rate_card.unit_basis AS rate_unit_basis,
    rate_card.minimum_billable_units AS rate_minimum_billable_units,
    rate_card.tier_start_units AS rate_tier_start_units,
    rate_card.tier_end_units AS rate_tier_end_units,
    rate_card.gross_price_microunits AS rate_gross_price_microunits,
    rate_card.tax_basis_points AS rate_tax_basis_points,
    rate_card.discount_basis_points AS rate_discount_basis_points,
    rate_card.credit_microunits_per_basis AS rate_credit_microunits_per_basis,
    rate_card.inr_per_native_unit_microunits AS rate_inr_per_native_unit_microunits,
    rate_card.conversion_source AS rate_conversion_source,
    rate_card.conversion_observed_at AS rate_conversion_observed_at,
    details.native_currency,
    details.estimated_native_cost_microunits,
    CASE
        WHEN details.id IS NULL THEN runs.estimated_cost_microunits
        ELSE details.estimated_inr_cost_microunits
    END AS estimated_inr_cost_microunits,
    COALESCE(details.reconciliation_status, 'legacy_unrated') AS reconciliation_status,
    runs.recorded_at,
    COALESCE(details.expires_at, {$runExpiresAt}) AS expires_at
FROM voice_provider_runs runs
LEFT JOIN speech_provider_attempt_details details
    ON details.organization_id = runs.organization_id
    AND details.voice_provider_run_id = runs.id
LEFT JOIN speech_rate_cards rate_card
    ON rate_card.id = details.speech_rate_card_id
SQL);

        DB::statement(<<<'SQL'
CREATE VIEW speech_provider_charge_usage_read_model AS
SELECT
    charges.id AS charge_id,
    usage.source_ledger,
    usage.source_id,
    usage.speech_provider_attempt_detail_id,
    usage.organization_id,
    usage.conversation_turn_id,
    usage.voice_recording_id,
    usage.conversation_session_id,
    usage.learner_membership_id,
    usage.environment,
    usage.conversation_mode,
    usage.semantic_operation,
    usage.route_role,
    usage.dispatch_kind,
    usage.provider_stage,
    usage.provider_key,
    usage.model_version,
    usage.provider_attempt,
    usage.api_version,
    usage.voice_key,
    usage.locale,
    usage.region,
    usage.configuration_sha256,
    usage.result_status,
    charges.component_key,
    charges.billable_unit_kind,
    charges.billable_unit_quantity,
    charges.speech_rate_card_id,
    rate_card.rate_version,
    rate_card.unit_basis AS rate_unit_basis,
    rate_card.minimum_billable_units AS rate_minimum_billable_units,
    rate_card.tier_start_units AS rate_tier_start_units,
    rate_card.tier_end_units AS rate_tier_end_units,
    rate_card.gross_price_microunits AS rate_gross_price_microunits,
    rate_card.tax_basis_points AS rate_tax_basis_points,
    rate_card.discount_basis_points AS rate_discount_basis_points,
    rate_card.credit_microunits_per_basis AS rate_credit_microunits_per_basis,
    rate_card.inr_per_native_unit_microunits AS rate_inr_per_native_unit_microunits,
    rate_card.conversion_source AS rate_conversion_source,
    rate_card.conversion_observed_at AS rate_conversion_observed_at,
    charges.native_currency,
    charges.estimated_native_cost_microunits,
    charges.estimated_inr_cost_microunits,
    charges.reconciliation_status,
    charges.recorded_at,
    charges.expires_at
FROM speech_provider_attempt_charges charges
JOIN speech_provider_usage_read_model usage
    ON usage.organization_id = charges.organization_id
    AND usage.speech_provider_attempt_detail_id = charges.speech_provider_attempt_detail_id
LEFT JOIN speech_rate_cards rate_card
    ON rate_card.id = charges.speech_rate_card_id
SQL);
    }

    private function addPostgresConstraints(): void
    {
        DB::statement("ALTER TABLE speech_rate_cards ADD CONSTRAINT speech_rate_cards_values_check CHECK (provider_stage IN ('stt', 'model', 'tts') AND rate_version ~ '^[a-zA-Z0-9._:-]{1,80}$' AND billable_unit_kind ~ '^[a-z][a-z0-9._-]{0,39}$' AND unit_basis > 0 AND (tier_end_units IS NULL OR tier_end_units > tier_start_units) AND billing_currency ~ '^[A-Z]{3}$' AND tax_basis_points <= 10000 AND discount_basis_points <= 10000 AND source_evidence_sha256 ~ '^[0-9a-f]{64}$' AND (effective_until IS NULL OR effective_until > effective_from))");
        DB::statement("ALTER TABLE speech_provider_attempt_details ADD CONSTRAINT speech_attempt_details_source_check CHECK ((ai_cost_ledger_id IS NOT NULL)::int + (voice_provider_run_id IS NOT NULL)::int = 1 AND provider_stage IN ('stt', 'model', 'tts') AND route_role IN ('primary', 'fallback', 'benchmark') AND dispatch_kind IN ('provider_dispatch', 'cache', 'replay', 'local_execution', 'unknown') AND result_status IN ('succeeded', 'failed', 'cancelled', 'partial') AND input_unit_kind ~ '^[a-z][a-z0-9._-]{0,39}$' AND output_unit_kind ~ '^[a-z][a-z0-9._-]{0,39}$' AND ((billable_unit_kind IS NULL AND billable_unit_quantity IS NULL) OR (billable_unit_kind IS NOT NULL AND billable_unit_quantity IS NOT NULL)) AND (configuration_sha256 IS NULL OR configuration_sha256 ~ '^[0-9a-f]{64}$') AND expires_at > recorded_at)");
        DB::statement("ALTER TABLE speech_provider_attempt_charges ADD CONSTRAINT speech_attempt_charges_values_check CHECK (component_key ~ '^[a-z][a-z0-9._-]{0,31}$' AND billable_unit_kind ~ '^[a-z][a-z0-9._-]{0,39}$' AND ((speech_rate_card_id IS NULL AND native_currency IS NULL AND estimated_native_cost_microunits IS NULL AND estimated_inr_cost_microunits IS NULL) OR (speech_rate_card_id IS NOT NULL AND native_currency ~ '^[A-Z]{3}$' AND estimated_native_cost_microunits IS NOT NULL AND estimated_inr_cost_microunits IS NOT NULL)) AND reconciliation_status IN ('unreconciled', 'not_billable', 'rate_missing', 'rate_stale', 'rate_ambiguous', 'usage_unclassified') AND expires_at > recorded_at)");
        DB::statement("ALTER TABLE speech_client_latency_samples ADD CONSTRAINT speech_client_latency_values_check CHECK (payload_sha256 ~ '^[0-9a-f]{64}$' AND sample_rate_bps BETWEEN 1 AND 10000 AND client_runtime IN ('web', 'android') AND network_class IN ('unknown', 'wifi', 'cellular', 'ethernet') AND playback_outcome IN ('started', 'unavailable', 'failed', 'cancelled') AND (playback_outcome <> 'started' OR release_to_first_audio_ms IS NOT NULL) AND expires_at > recorded_at)");
        DB::statement("ALTER TABLE speech_usage_daily_aggregates ADD CONSTRAINT speech_usage_daily_values_check CHECK (aggregate_kind IN ('provider_attempt', 'provider_charge', 'client_latency') AND provider_stage IN ('stt', 'model', 'tts', 'end_to_end') AND route_role IN ('primary', 'fallback', 'benchmark', 'client') AND result_status IN ('succeeded', 'failed', 'cancelled', 'partial', 'started', 'unavailable') AND dispatch_kind IN ('provider_dispatch', 'cache', 'replay', 'local_execution', 'unknown', 'client_sample') AND billable_unit_kind ~ '^[a-z][a-z0-9._-]{0,39}$' AND (native_currency = 'unknown' OR native_currency ~ '^[A-Z]{3}$') AND ((aggregate_kind IN ('provider_attempt', 'provider_charge') AND sample_rate_bps = 0) OR (aggregate_kind = 'client_latency' AND sample_rate_bps BETWEEN 1 AND 10000)))");
        DB::statement("ALTER TABLE speech_provider_statements ADD CONSTRAINT speech_provider_statements_values_check CHECK (statement_reference_sha256 ~ '^[0-9a-f]{64}$' AND payload_sha256 ~ '^[0-9a-f]{64}$' AND evidence_sha256 ~ '^[0-9a-f]{64}$' AND billing_currency ~ '^[A-Z]{3}$' AND period_end >= period_start AND net_cost_microunits = gross_cost_microunits + tax_microunits - discount_microunits - credit_microunits)");
        DB::statement("ALTER TABLE speech_statement_reconciliations ADD CONSTRAINT speech_statement_reconciliations_values_check CHECK (provider_stage IN ('stt', 'model', 'tts') AND status IN ('reconciled', 'variance_exceeds_tolerance') AND evidence_sha256 ~ '^[0-9a-f]{64}$')");
    }

    private function addPostgresAppendOnlyTriggers(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS ai_cost_ledger_immutable ON ai_cost_ledger');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_speech_observability_append_only()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE'
       AND current_setting('app.speech_usage_retention_purge', true) = 'on' THEN
        RETURN OLD;
    END IF;
    RAISE EXCEPTION 'speech observability evidence is append-only';
END;
$$ LANGUAGE plpgsql;
SQL);

        foreach ([
            'ai_cost_ledger',
            'voice_provider_runs',
            'speech_rate_cards',
            'speech_provider_attempt_details',
            'speech_provider_attempt_charges',
            'speech_client_latency_samples',
            'speech_provider_statements',
            'speech_statement_reconciliations',
        ] as $table) {
            DB::statement(<<<SQL
CREATE TRIGGER {$table}_speech_append_only
BEFORE UPDATE OR DELETE ON {$table}
FOR EACH ROW EXECUTE FUNCTION protect_speech_observability_append_only()
SQL);
        }
    }
};
