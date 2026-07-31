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
        Schema::create('voice_consent_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_membership_id');
            $table->foreignUlid('actor_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('purpose', 64);
            $table->string('policy_version', 32);
            $table->string('notice_revision', 64);
            $table->char('notice_sha256', 64);
            $table->string('requested_locale', 16);
            $table->string('resolved_locale', 16);
            $table->string('state', 16);
            $table->string('interaction_id', 36);
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->index(
                [
                    'organization_id',
                    'learner_membership_id',
                    'purpose',
                    'effective_at',
                ],
                'voice_consent_latest_index',
            );
            $table->foreign(
                [
                    'organization_id',
                    'actor_user_id',
                    'learner_membership_id',
                ],
                'voice_consent_actor_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('voice_recordings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->ulid('upload_consent_event_id');
            $table->ulid('provider_consent_event_id');
            $table->string('status', 48);
            $table->string('source_object_key', 160)->nullable();
            $table->char('source_sha256', 64);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('duration_ms');
            $table->string('mime_type', 64);
            $table->string('codec', 32);
            $table->unsignedInteger('sample_rate_hz');
            $table->unsignedSmallInteger('channels');
            $table->text('transcript_ciphertext')->nullable();
            $table->char('transcript_candidate_sha256', 64)->nullable();
            $table->unsignedSmallInteger('transcript_confidence_bps')->nullable();
            $table->string('transcript_action', 16)->nullable();
            $table->timestampTz('source_delete_by');
            $table->timestampTz('source_deleted_at')->nullable();
            $table->timestampTz('transcript_expires_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'id', 'learner_membership_id'],
                'voice_recordings_membership_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'voice_recordings_identity_unique',
            );
            $table->index(
                [
                    'organization_id',
                    'learner_membership_id',
                    'status',
                    'source_delete_by',
                ],
                'voice_recordings_lifecycle_index',
            );
            $table->foreign(
                [
                    'organization_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'voice_recordings_session_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('conversation_sessions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'upload_consent_event_id'],
                'voice_recordings_upload_consent_fk',
            )->references(['organization_id', 'id'])
                ->on('voice_consent_events')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'provider_consent_event_id'],
                'voice_recordings_provider_consent_fk',
            )->references(['organization_id', 'id'])
                ->on('voice_consent_events')
                ->restrictOnDelete();
        });

        Schema::create('voice_provider_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('voice_recording_id');
            $table->ulid('conversation_session_id');
            $table->ulid('learner_membership_id');
            $table->string('provider_stage', 16);
            $table->string('provider_key', 64);
            $table->string('model_version', 100);
            $table->unsignedSmallInteger('provider_attempt');
            $table->string('result_status', 24);
            $table->string('safe_failure_code', 64)->nullable();
            $table->unsignedInteger('latency_ms');
            $table->unsignedInteger('input_units');
            $table->unsignedInteger('output_units');
            $table->unsignedBigInteger('estimated_cost_microunits');
            $table->timestampTz('recorded_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'voice_recording_id',
                    'provider_stage',
                    'provider_attempt',
                ],
                'voice_provider_runs_attempt_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'voice_recording_id',
                    'conversation_session_id',
                    'learner_membership_id',
                ],
                'voice_provider_runs_recording_fk',
            )->references([
                'organization_id',
                'id',
                'conversation_session_id',
                'learner_membership_id',
            ])->on('voice_recordings')
                ->restrictOnDelete();
        });

        Schema::create('voice_deletion_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('voice_recording_id');
            $table->ulid('learner_membership_id');
            $table->string('reason', 32);
            $table->string('status', 24);
            $table->boolean('source_deleted');
            $table->boolean('transcript_deleted');
            $table->string('provider_deletion_status', 24);
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'voice_recording_id'],
                'voice_deletion_recording_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'voice_recording_id',
                    'learner_membership_id',
                ],
                'voice_deletion_recording_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('voice_recordings')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE voice_consent_events ADD CONSTRAINT voice_consent_values_check CHECK (purpose IN ('voice_upload_and_transcription', 'conversation_provider_processing', 'transcript_history') AND state IN ('granted', 'withdrawn') AND notice_sha256 ~ '^[0-9a-f]{64}$')",
            );
            DB::statement(
                "ALTER TABLE voice_recordings ADD CONSTRAINT voice_recordings_values_check CHECK (status IN ('quarantined', 'validated', 'processing', 'awaiting_learner_confirmation', 'confirmed', 'corrected', 'discarded', 'rejected_deleted', 'provider_failed_recoverable', 'expired_deleted', 'withdrawn_deleted') AND source_sha256 ~ '^[0-9a-f]{64}$' AND byte_size > 0 AND byte_size <= 10485760 AND duration_ms > 0 AND duration_ms <= 120000 AND mime_type = 'audio/wav' AND codec = 'pcm_s16le' AND channels = 1 AND sample_rate_hz BETWEEN 8000 AND 48000 AND (transcript_candidate_sha256 IS NULL OR transcript_candidate_sha256 ~ '^[0-9a-f]{64}$') AND (transcript_confidence_bps IS NULL OR transcript_confidence_bps <= 10000))",
            );
            DB::statement(
                "ALTER TABLE voice_provider_runs ADD CONSTRAINT voice_provider_runs_values_check CHECK (provider_stage IN ('stt', 'tts') AND provider_attempt > 0 AND result_status IN ('succeeded', 'failed') AND (result_status = 'succeeded' OR safe_failure_code IS NOT NULL))",
            );
            DB::statement(
                "ALTER TABLE voice_deletion_requests ADD CONSTRAINT voice_deletion_values_check CHECK (reason IN ('learner_request', 'withdrawal', 'expiry', 'rejection') AND status IN ('pending', 'completed', 'partially_completed', 'restricted') AND provider_deletion_status IN ('not_dispatched', 'not_required', 'requested', 'confirmed', 'failed'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_deletion_requests');
        Schema::dropIfExists('voice_provider_runs');
        Schema::dropIfExists('voice_recordings');
        Schema::dropIfExists('voice_consent_events');
    }
};
