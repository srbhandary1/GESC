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
        Schema::create('media_transcription_campaigns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('state', 32)->default('running');
            $table->string('approval_reference', 40);
            $table->char('approved_request_sha256', 64);
            $table->char('manifest_sha256', 64);
            $table->string('provider_key', 64);
            $table->string('provider_model', 160);
            $table->string('provider_region', 64);
            $table->string('provider_api_version', 32);
            $table->string('locale', 16);
            $table->char('configuration_sha256', 64);
            $table->char('vendor_evidence_sha256', 64);
            $table->char('price_evidence_sha256', 64);
            $table->string('credential_version', 120);
            $table->unsignedSmallInteger('logical_asset_cap');
            $table->unsignedSmallInteger('http_attempt_cap');
            $table->unsignedBigInteger('uploaded_byte_cap');
            $table->unsignedBigInteger('billable_millisecond_cap');
            $table->unsignedBigInteger('estimated_cost_microunit_cap');
            $table->unsignedSmallInteger('reserved_http_attempts')->default(0);
            $table->unsignedBigInteger('reserved_uploaded_bytes')->default(0);
            $table->unsignedBigInteger('reserved_billable_milliseconds')->default(0);
            $table->unsignedBigInteger('reserved_estimated_cost_microunits')->default(0);
            $table->ulid('initiated_by_membership_id');
            $table->timestampTz('started_at');
            $table->timestampTz('terminal_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'id', 'program_id'],
                'mt_campaigns_program_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'approval_reference',
                    'approved_request_sha256',
                ],
                'mt_campaigns_approval_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id'],
                'mt_campaigns_program_fk',
            )->references(['organization_id', 'id'])
                ->on('programs')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'initiated_by_membership_id'],
                'mt_campaigns_initiator_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'state', 'updated_at', 'id'],
                'mt_campaigns_state_index',
            );
        });

        Schema::create('media_transcription_campaign_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('campaign_id');
            $table->ulid('program_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('part_key', 80);
            $table->ulid('media_asset_id');
            $table->ulid('selected_scan_id');
            $table->ulid('media_rights_revision_id');
            $table->string('rights_evidence_status', 24);
            $table->char('rights_evidence_hash', 64);
            $table->char('source_sha256', 64);
            $table->unsignedBigInteger('source_bytes');
            $table->unsignedBigInteger('source_duration_milliseconds');
            $table->string('locale', 16);
            $table->string('state', 32)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->ulid('candidate_provider_attempt_id')->nullable();
            $table->char('candidate_sha256', 64)->nullable();
            $table->char('response_metadata_sha256', 64)->nullable();
            $table->timestampTz('candidate_stored_at')->nullable();
            $table->timestampTz('candidate_expires_at')->nullable();
            $table->timestampTz('reconciled_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'campaign_id', 'id'],
                'mt_campaign_assets_campaign_id_unique',
            );
            $table->unique(
                ['organization_id', 'campaign_id', 'sequence'],
                'mt_campaign_assets_sequence_unique',
            );
            $table->unique(
                ['organization_id', 'campaign_id', 'media_asset_id'],
                'mt_campaign_assets_media_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'campaign_id',
                    'id',
                    'media_asset_id',
                    'candidate_sha256',
                ],
                'mt_campaign_assets_reconciliation_unique',
            );
            $table->foreign(
                ['organization_id', 'campaign_id', 'program_id'],
                'mt_campaign_assets_campaign_fk',
            )->references(['organization_id', 'id', 'program_id'])
                ->on('media_transcription_campaigns')
                ->cascadeOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id', 'source_sha256'],
                'mt_campaign_assets_media_fk',
            )->references(['organization_id', 'id', 'asset_hash'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'selected_scan_id', 'source_sha256'],
                'mt_campaign_assets_scan_fk',
            )->references(['organization_id', 'id', 'object_hash'])
                ->on('media_security_scans')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id', 'selected_scan_id'],
                'mt_campaign_assets_lifecycle_fk',
            )->references([
                'organization_id',
                'media_asset_id',
                'selected_scan_id',
            ])->on('media_asset_lifecycles')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'media_asset_id',
                    'media_rights_revision_id',
                    'rights_evidence_status',
                    'rights_evidence_hash',
                    'source_sha256',
                ],
                'mt_campaign_assets_rights_fk',
            )->references([
                'organization_id',
                'program_id',
                'media_asset_id',
                'id',
                'evidence_status',
                'evidence_hash',
                'selected_asset_hash',
            ])->on('media_rights_revisions')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'campaign_id', 'state', 'sequence'],
                'mt_campaign_assets_state_index',
            );
            $table->index(
                ['state', 'candidate_expires_at'],
                'mt_campaign_assets_expiry_index',
            );
        });

        Schema::create('media_transcription_provider_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('campaign_id');
            $table->ulid('campaign_asset_id');
            $table->ulid('reservation_event_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->string('provider_key', 64);
            $table->string('provider_model', 160);
            $table->string('provider_region', 64);
            $table->string('provider_api_version', 32);
            $table->char('configuration_sha256', 64);
            $table->char('input_sha256', 64);
            $table->unsignedBigInteger('input_bytes');
            $table->unsignedBigInteger('input_duration_milliseconds');
            $table->string('status', 32);
            $table->string('safe_failure_code', 100)->nullable();
            $table->boolean('retryable')->default(false);
            $table->unsignedInteger('latency_milliseconds')->default(0);
            $table->unsignedBigInteger('usage_milliseconds')->default(0);
            $table->unsignedBigInteger('estimated_cost_microunits');
            $table->char('candidate_sha256', 64)->nullable();
            $table->char('response_metadata_sha256', 64)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'campaign_asset_id', 'attempt_number'],
                'mt_provider_attempts_number_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'campaign_id',
                    'campaign_asset_id',
                    'id',
                ],
                'mt_provider_attempts_asset_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'campaign_id',
                    'campaign_asset_id',
                    'id',
                    'candidate_sha256',
                ],
                'mt_provider_attempts_candidate_unique',
            );
            $table->foreign(
                ['organization_id', 'campaign_id', 'campaign_asset_id'],
                'mt_provider_attempts_asset_fk',
            )->references(['organization_id', 'campaign_id', 'id'])
                ->on('media_transcription_campaign_assets')
                ->cascadeOnDelete();
            $table->index(
                ['organization_id', 'campaign_id', 'created_at', 'id'],
                'mt_provider_attempts_timeline_index',
            );
        });

        Schema::create('media_transcription_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('campaign_id');
            $table->ulid('campaign_asset_id')->nullable();
            $table->ulid('provider_attempt_id')->nullable();
            $table->unsignedSmallInteger('attempt_number')->nullable();
            $table->unsignedBigInteger('reserved_uploaded_bytes')->nullable();
            $table->unsignedBigInteger('reserved_billable_milliseconds')->nullable();
            $table->unsignedBigInteger('reserved_estimated_cost_microunits')->nullable();
            $table->string('event_type', 80);
            $table->unsignedInteger('campaign_version');
            $table->unsignedInteger('asset_version')->nullable();
            $table->ulid('actor_membership_id')->nullable();
            $table->string('safe_failure_code', 100)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'campaign_id',
                    'campaign_asset_id',
                    'id',
                    'provider_attempt_id',
                ],
                'mt_events_asset_id_unique',
            );
            $table->foreign(
                ['organization_id', 'campaign_id'],
                'mt_events_campaign_fk',
            )->references(['organization_id', 'id'])
                ->on('media_transcription_campaigns')
                ->cascadeOnDelete();
            $table->foreign(
                ['organization_id', 'campaign_id', 'campaign_asset_id'],
                'mt_events_asset_fk',
            )->references(['organization_id', 'campaign_id', 'id'])
                ->on('media_transcription_campaign_assets')
                ->cascadeOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'mt_events_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'campaign_id', 'occurred_at', 'id'],
                'mt_events_timeline_index',
            );
            $table->index(
                ['organization_id', 'provider_attempt_id', 'event_type'],
                'mt_events_attempt_index',
            );
        });

        Schema::table('media_transcription_provider_attempts', function (Blueprint $table): void {
            $table->foreign(
                [
                    'organization_id',
                    'campaign_id',
                    'campaign_asset_id',
                    'reservation_event_id',
                    'id',
                ],
                'mt_provider_attempts_reservation_fk',
            )->references([
                'organization_id',
                'campaign_id',
                'campaign_asset_id',
                'id',
                'provider_attempt_id',
            ])->on('media_transcription_events')
                ->cascadeOnDelete();
        });

        Schema::table('media_transcription_campaign_assets', function (Blueprint $table): void {
            $table->foreign(
                [
                    'organization_id',
                    'campaign_id',
                    'id',
                    'candidate_provider_attempt_id',
                    'candidate_sha256',
                ],
                'mt_campaign_assets_candidate_attempt_fk',
            )->references([
                'organization_id',
                'campaign_id',
                'campaign_asset_id',
                'id',
                'candidate_sha256',
            ])->on('media_transcription_provider_attempts')
                ->cascadeOnDelete();
        });

        Schema::create('media_transcription_candidate_deletions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('campaign_id');
            $table->ulid('campaign_asset_id');
            $table->ulid('provider_attempt_id');
            $table->char('candidate_sha256', 64);
            $table->string('state', 24)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at');
            $table->timestampTz('hard_deadline_at');
            $table->string('last_error_code', 100)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'campaign_asset_id',
                    'provider_attempt_id',
                    'candidate_sha256',
                ],
                'mt_candidate_deletions_candidate_unique',
            );
            $table->foreign(
                ['organization_id', 'campaign_id', 'campaign_asset_id'],
                'mt_candidate_deletions_asset_fk',
            )->references(['organization_id', 'campaign_id', 'id'])
                ->on('media_transcription_campaign_assets')
                ->cascadeOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'campaign_id',
                    'campaign_asset_id',
                    'provider_attempt_id',
                    'candidate_sha256',
                ],
                'mt_candidate_deletions_attempt_fk',
            )->references([
                'organization_id',
                'campaign_id',
                'campaign_asset_id',
                'id',
                'candidate_sha256',
            ])->on('media_transcription_provider_attempts')
                ->cascadeOnDelete();
            $table->index(
                ['state', 'next_attempt_at', 'id'],
                'mt_candidate_deletions_due_index',
            );
        });

        Schema::create('media_transcription_reconciliations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('campaign_id');
            $table->ulid('campaign_asset_id');
            $table->ulid('media_asset_id');
            $table->char('candidate_sha256', 64);
            $table->string('outcome', 32);
            $table->ulid('metadata_revision_id')->nullable();
            $table->char('metadata_content_hash', 64)->nullable();
            $table->ulid('reconciled_by_membership_id');
            $table->timestampTz('reconciled_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'campaign_asset_id'],
                'mt_reconciliations_asset_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'campaign_id',
                    'campaign_asset_id',
                    'media_asset_id',
                    'candidate_sha256',
                ],
                'mt_reconciliations_asset_fk',
            )->references([
                'organization_id',
                'campaign_id',
                'id',
                'media_asset_id',
                'candidate_sha256',
            ])
                ->on('media_transcription_campaign_assets')
                ->cascadeOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'media_asset_id',
                    'metadata_revision_id',
                    'metadata_content_hash',
                ],
                'mt_reconciliations_metadata_fk',
            )->references([
                'organization_id',
                'media_asset_id',
                'id',
                'content_hash',
            ])->on('media_asset_metadata_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'reconciled_by_membership_id'],
                'mt_reconciliations_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        $this->addPostgresProtection();
    }

    public function down(): void
    {
        $this->dropPostgresProtection();
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($sqlite) {
            Schema::disableForeignKeyConstraints();
        }

        Schema::dropIfExists('media_transcription_reconciliations');
        Schema::dropIfExists('media_transcription_candidate_deletions');
        if (! $sqlite) {
            Schema::table('media_transcription_campaign_assets', function (Blueprint $table): void {
                $table->dropForeign('mt_campaign_assets_candidate_attempt_fk');
            });
        }
        Schema::dropIfExists('media_transcription_provider_attempts');
        Schema::dropIfExists('media_transcription_events');
        Schema::dropIfExists('media_transcription_campaign_assets');
        Schema::dropIfExists('media_transcription_campaigns');
        if ($sqlite) {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function addPostgresProtection(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
ALTER TABLE media_transcription_campaigns
ADD CONSTRAINT mt_campaigns_values_check
CHECK (
    version > 0
    AND state IN ('running', 'provider_complete', 'completed', 'failed')
    AND approval_reference = 'APP-0052'
    AND approved_request_sha256 ~ '^[0-9a-f]{64}$'
    AND manifest_sha256 ~ '^[0-9a-f]{64}$'
    AND configuration_sha256 ~ '^[0-9a-f]{64}$'
    AND vendor_evidence_sha256 ~ '^[0-9a-f]{64}$'
    AND price_evidence_sha256 ~ '^[0-9a-f]{64}$'
    AND logical_asset_cap = 4
    AND http_attempt_cap = 8
    AND uploaded_byte_cap = 37520380
    AND billable_millisecond_cap = 2345026
    AND estimated_cost_microunit_cap = 100000000
    AND reserved_http_attempts <= http_attempt_cap
    AND reserved_uploaded_bytes <= uploaded_byte_cap
    AND reserved_billable_milliseconds <= billable_millisecond_cap
    AND reserved_estimated_cost_microunits <= estimated_cost_microunit_cap
    AND ((state IN ('completed', 'failed')) = (terminal_at IS NOT NULL))
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_transcription_campaign_assets
ADD CONSTRAINT mt_campaign_assets_values_check
CHECK (
    sequence BETWEEN 1 AND 4
    AND part_key ~ '^[a-z][a-z0-9_.-]{0,79}$'
    AND rights_evidence_status = 'approved'
    AND rights_evidence_hash ~ '^[0-9a-f]{64}$'
    AND source_sha256 ~ '^[0-9a-f]{64}$'
    AND source_bytes > 0
    AND source_duration_milliseconds > 0
    AND locale = 'en-IN'
    AND state IN ('pending', 'processing', 'retry_pending', 'candidate_ready', 'confirmed', 'corrected', 'discarded', 'needs_attention', 'expired', 'failed')
    AND version > 0
    AND (
        (state IN ('candidate_ready', 'confirmed', 'corrected', 'discarded', 'needs_attention', 'expired')
            AND candidate_provider_attempt_id IS NOT NULL
            AND candidate_sha256 ~ '^[0-9a-f]{64}$'
            AND response_metadata_sha256 ~ '^[0-9a-f]{64}$'
            AND candidate_stored_at IS NOT NULL
            AND candidate_expires_at > candidate_stored_at)
        OR
        (state IN ('pending', 'processing', 'retry_pending', 'failed')
            AND candidate_provider_attempt_id IS NULL
            AND candidate_sha256 IS NULL
            AND response_metadata_sha256 IS NULL
            AND candidate_stored_at IS NULL
            AND candidate_expires_at IS NULL)
    )
    AND ((state IN ('confirmed', 'corrected', 'discarded', 'needs_attention')) = (reconciled_at IS NOT NULL))
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_transcription_provider_attempts
ADD CONSTRAINT mt_provider_attempts_values_check
CHECK (
    attempt_number BETWEEN 1 AND 2
    AND configuration_sha256 ~ '^[0-9a-f]{64}$'
    AND input_sha256 ~ '^[0-9a-f]{64}$'
    AND input_bytes > 0
    AND input_duration_milliseconds > 0
    AND status IN ('succeeded', 'failed', 'failed_uncertain')
    AND estimated_cost_microunits > 0
    AND completed_at >= started_at
    AND completed_at = created_at
    AND (
        (status = 'succeeded' AND safe_failure_code IS NULL AND retryable = FALSE
            AND candidate_sha256 ~ '^[0-9a-f]{64}$'
            AND response_metadata_sha256 ~ '^[0-9a-f]{64}$')
        OR
        (status IN ('failed', 'failed_uncertain')
            AND safe_failure_code ~ '^[a-z][a-z0-9_.-]{0,99}$'
            AND (candidate_sha256 IS NULL OR candidate_sha256 ~ '^[0-9a-f]{64}$')
            AND (response_metadata_sha256 IS NULL OR response_metadata_sha256 ~ '^[0-9a-f]{64}$')
            AND (response_metadata_sha256 IS NULL OR candidate_sha256 IS NOT NULL))
    )
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_transcription_events
ADD CONSTRAINT mt_events_values_check
CHECK (
    event_type IN (
        'campaign.started', 'provider_attempt.reserved',
        'provider_attempt.succeeded', 'provider_attempt.failed',
        'provider_attempt.interrupted', 'campaign.provider_complete',
        'campaign.failed', 'candidate.reconciled',
        'candidate.expired', 'candidate.deletion_requested',
        'candidate.deleted', 'candidate.deletion_failed',
        'candidate.deletion_dead_letter', 'campaign.completed'
    )
    AND campaign_version > 0
    AND (asset_version IS NULL OR asset_version > 0)
    AND (safe_failure_code IS NULL OR safe_failure_code ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND (
        (event_type = 'provider_attempt.reserved'
            AND provider_attempt_id IS NOT NULL
            AND campaign_asset_id IS NOT NULL
            AND attempt_number BETWEEN 1 AND 2
            AND reserved_uploaded_bytes > 0
            AND reserved_billable_milliseconds > 0
            AND reserved_estimated_cost_microunits > 0)
        OR
        (event_type <> 'provider_attempt.reserved'
            AND attempt_number IS NULL
            AND reserved_uploaded_bytes IS NULL
            AND reserved_billable_milliseconds IS NULL
            AND reserved_estimated_cost_microunits IS NULL)
    )
    AND (
        event_type NOT IN (
            'provider_attempt.succeeded',
            'provider_attempt.failed',
            'provider_attempt.interrupted'
        )
        OR (provider_attempt_id IS NOT NULL AND campaign_asset_id IS NOT NULL)
    )
    AND occurred_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_transcription_candidate_deletions
ADD CONSTRAINT mt_candidate_deletions_values_check
CHECK (
    candidate_sha256 ~ '^[0-9a-f]{64}$'
    AND state IN ('pending', 'retrying', 'completed', 'dead_letter')
    AND attempt_count BETWEEN 0 AND 5
    AND hard_deadline_at > created_at
    AND (last_error_code IS NULL OR last_error_code ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND ((state = 'completed') = (completed_at IS NOT NULL))
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_transcription_reconciliations
ADD CONSTRAINT mt_reconciliations_values_check
CHECK (
    candidate_sha256 ~ '^[0-9a-f]{64}$'
    AND outcome IN ('confirmed', 'corrected', 'discarded', 'needs_attention')
    AND (
        (outcome IN ('confirmed', 'corrected')
            AND metadata_revision_id IS NOT NULL
            AND metadata_content_hash ~ '^[0-9a-f]{64}$')
        OR
        (outcome IN ('discarded', 'needs_attention')
            AND metadata_revision_id IS NULL
            AND metadata_content_hash IS NULL)
    )
    AND reconciled_at = created_at
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mt_reject_history_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF current_setting('app.media_transcription_retention_purge', true) = 'on' THEN
        RETURN OLD;
    END IF;

    RAISE EXCEPTION 'media transcription evidence is append-only';
END;
$$;

CREATE TRIGGER mt_provider_attempts_append_only
BEFORE UPDATE OR DELETE ON media_transcription_provider_attempts
FOR EACH ROW EXECUTE FUNCTION mt_reject_history_mutation();

CREATE TRIGGER mt_events_append_only
BEFORE UPDATE OR DELETE ON media_transcription_events
FOR EACH ROW EXECUTE FUNCTION mt_reject_history_mutation();

CREATE OR REPLACE FUNCTION mt_event_attempt_relationship()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.event_type IN (
        'provider_attempt.succeeded',
        'provider_attempt.failed',
        'provider_attempt.interrupted'
    ) AND NOT EXISTS (
        SELECT 1
        FROM media_transcription_provider_attempts attempts
        WHERE attempts.organization_id = NEW.organization_id
          AND attempts.campaign_id = NEW.campaign_id
          AND attempts.campaign_asset_id = NEW.campaign_asset_id
          AND attempts.id = NEW.provider_attempt_id
    ) THEN
        RAISE EXCEPTION 'invalid media transcription event attempt relationship';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER mt_events_attempt_relationship
BEFORE INSERT ON media_transcription_events
FOR EACH ROW EXECUTE FUNCTION mt_event_attempt_relationship();

CREATE TRIGGER mt_reconciliations_append_only
BEFORE UPDATE OR DELETE ON media_transcription_reconciliations
FOR EACH ROW EXECUTE FUNCTION mt_reject_history_mutation();

CREATE OR REPLACE FUNCTION mt_campaign_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.program_id IS DISTINCT FROM NEW.program_id
        OR OLD.approval_reference IS DISTINCT FROM NEW.approval_reference
        OR OLD.approved_request_sha256 IS DISTINCT FROM NEW.approved_request_sha256
        OR OLD.manifest_sha256 IS DISTINCT FROM NEW.manifest_sha256
        OR OLD.provider_key IS DISTINCT FROM NEW.provider_key
        OR OLD.provider_model IS DISTINCT FROM NEW.provider_model
        OR OLD.provider_region IS DISTINCT FROM NEW.provider_region
        OR OLD.provider_api_version IS DISTINCT FROM NEW.provider_api_version
        OR OLD.locale IS DISTINCT FROM NEW.locale
        OR OLD.configuration_sha256 IS DISTINCT FROM NEW.configuration_sha256
        OR OLD.vendor_evidence_sha256 IS DISTINCT FROM NEW.vendor_evidence_sha256
        OR OLD.price_evidence_sha256 IS DISTINCT FROM NEW.price_evidence_sha256
        OR OLD.credential_version IS DISTINCT FROM NEW.credential_version
        OR OLD.logical_asset_cap IS DISTINCT FROM NEW.logical_asset_cap
        OR OLD.http_attempt_cap IS DISTINCT FROM NEW.http_attempt_cap
        OR OLD.uploaded_byte_cap IS DISTINCT FROM NEW.uploaded_byte_cap
        OR OLD.billable_millisecond_cap IS DISTINCT FROM NEW.billable_millisecond_cap
        OR OLD.estimated_cost_microunit_cap IS DISTINCT FROM NEW.estimated_cost_microunit_cap
        OR OLD.initiated_by_membership_id IS DISTINCT FROM NEW.initiated_by_membership_id
        OR OLD.started_at IS DISTINCT FROM NEW.started_at
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR NEW.version <> OLD.version + 1
        OR NEW.reserved_http_attempts < OLD.reserved_http_attempts
        OR NEW.reserved_uploaded_bytes < OLD.reserved_uploaded_bytes
        OR NEW.reserved_billable_milliseconds < OLD.reserved_billable_milliseconds
        OR NEW.reserved_estimated_cost_microunits < OLD.reserved_estimated_cost_microunits
        OR OLD.state IN ('completed', 'failed')
        OR (OLD.state = 'running' AND NEW.state NOT IN ('running', 'provider_complete', 'failed'))
        OR (OLD.state = 'provider_complete' AND NEW.state NOT IN ('provider_complete', 'completed', 'failed'))
    THEN
        RAISE EXCEPTION 'invalid media transcription campaign transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER mt_campaigns_transition
BEFORE UPDATE ON media_transcription_campaigns
FOR EACH ROW EXECUTE FUNCTION mt_campaign_transition();

CREATE TRIGGER mt_campaigns_no_delete
BEFORE DELETE ON media_transcription_campaigns
FOR EACH ROW EXECUTE FUNCTION mt_reject_history_mutation();

CREATE OR REPLACE FUNCTION mt_campaign_asset_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.campaign_id IS DISTINCT FROM NEW.campaign_id
        OR OLD.program_id IS DISTINCT FROM NEW.program_id
        OR OLD.sequence IS DISTINCT FROM NEW.sequence
        OR OLD.part_key IS DISTINCT FROM NEW.part_key
        OR OLD.media_asset_id IS DISTINCT FROM NEW.media_asset_id
        OR OLD.selected_scan_id IS DISTINCT FROM NEW.selected_scan_id
        OR OLD.media_rights_revision_id IS DISTINCT FROM NEW.media_rights_revision_id
        OR OLD.rights_evidence_status IS DISTINCT FROM NEW.rights_evidence_status
        OR OLD.rights_evidence_hash IS DISTINCT FROM NEW.rights_evidence_hash
        OR OLD.source_sha256 IS DISTINCT FROM NEW.source_sha256
        OR OLD.source_bytes IS DISTINCT FROM NEW.source_bytes
        OR OLD.source_duration_milliseconds IS DISTINCT FROM NEW.source_duration_milliseconds
        OR OLD.locale IS DISTINCT FROM NEW.locale
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR (OLD.candidate_sha256 IS NOT NULL AND (
            OLD.candidate_provider_attempt_id IS DISTINCT FROM NEW.candidate_provider_attempt_id
            OR OLD.candidate_sha256 IS DISTINCT FROM NEW.candidate_sha256
            OR OLD.response_metadata_sha256 IS DISTINCT FROM NEW.response_metadata_sha256
            OR OLD.candidate_stored_at IS DISTINCT FROM NEW.candidate_stored_at
            OR OLD.candidate_expires_at IS DISTINCT FROM NEW.candidate_expires_at
        ))
        OR NEW.version <> OLD.version + 1
        OR OLD.state IN ('confirmed', 'corrected', 'discarded', 'needs_attention', 'expired', 'failed')
        OR (OLD.state = 'pending' AND NEW.state NOT IN ('processing', 'failed'))
        OR (OLD.state = 'processing' AND NEW.state NOT IN ('candidate_ready', 'retry_pending', 'failed'))
        OR (OLD.state = 'retry_pending' AND NEW.state NOT IN ('processing', 'failed'))
        OR (OLD.state = 'candidate_ready' AND NEW.state NOT IN ('confirmed', 'corrected', 'discarded', 'needs_attention', 'expired'))
    THEN
        RAISE EXCEPTION 'invalid media transcription asset transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER mt_campaign_assets_transition
BEFORE UPDATE ON media_transcription_campaign_assets
FOR EACH ROW EXECUTE FUNCTION mt_campaign_asset_transition();

CREATE TRIGGER mt_campaign_assets_no_delete
BEFORE DELETE ON media_transcription_campaign_assets
FOR EACH ROW EXECUTE FUNCTION mt_reject_history_mutation();

CREATE OR REPLACE FUNCTION mt_candidate_deletion_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.campaign_id IS DISTINCT FROM NEW.campaign_id
        OR OLD.campaign_asset_id IS DISTINCT FROM NEW.campaign_asset_id
        OR OLD.provider_attempt_id IS DISTINCT FROM NEW.provider_attempt_id
        OR OLD.candidate_sha256 IS DISTINCT FROM NEW.candidate_sha256
        OR OLD.hard_deadline_at IS DISTINCT FROM NEW.hard_deadline_at
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR NEW.attempt_count <> OLD.attempt_count + 1
        OR OLD.state IN ('completed', 'dead_letter')
        OR NEW.state NOT IN ('retrying', 'completed', 'dead_letter')
    THEN
        RAISE EXCEPTION 'invalid media transcription candidate deletion transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER mt_candidate_deletions_transition
BEFORE UPDATE ON media_transcription_candidate_deletions
FOR EACH ROW EXECUTE FUNCTION mt_candidate_deletion_transition();

CREATE TRIGGER mt_candidate_deletions_no_delete
BEFORE DELETE ON media_transcription_candidate_deletions
FOR EACH ROW EXECUTE FUNCTION mt_reject_history_mutation();
SQL);
    }

    private function dropPostgresProtection(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS mt_candidate_deletions_no_delete ON media_transcription_candidate_deletions;
DROP TRIGGER IF EXISTS mt_candidate_deletions_transition ON media_transcription_candidate_deletions;
DROP TRIGGER IF EXISTS mt_campaign_assets_no_delete ON media_transcription_campaign_assets;
DROP TRIGGER IF EXISTS mt_campaign_assets_transition ON media_transcription_campaign_assets;
DROP TRIGGER IF EXISTS mt_campaigns_no_delete ON media_transcription_campaigns;
DROP TRIGGER IF EXISTS mt_campaigns_transition ON media_transcription_campaigns;
DROP TRIGGER IF EXISTS mt_reconciliations_append_only ON media_transcription_reconciliations;
DROP TRIGGER IF EXISTS mt_events_attempt_relationship ON media_transcription_events;
DROP TRIGGER IF EXISTS mt_events_append_only ON media_transcription_events;
DROP TRIGGER IF EXISTS mt_provider_attempts_append_only ON media_transcription_provider_attempts;
DROP FUNCTION IF EXISTS mt_candidate_deletion_transition();
DROP FUNCTION IF EXISTS mt_campaign_asset_transition();
DROP FUNCTION IF EXISTS mt_campaign_transition();
DROP FUNCTION IF EXISTS mt_event_attempt_relationship();
DROP FUNCTION IF EXISTS mt_reject_history_mutation();
SQL);
    }
};
