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
        Schema::create('media_uploads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('candidate_asset_id');
            $table->string('asset_kind', 48);
            $table->string('safe_label', 160);
            $table->string('state', 32)->default('uploading');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('total_bytes');
            $table->char('whole_object_hash', 64);
            $table->unsignedInteger('part_size_bytes');
            $table->unsignedInteger('expected_part_count');
            $table->unsignedInteger('received_part_count')->default(0);
            $table->string('quarantine_object_key', 500)->nullable();
            $table->string('accepted_object_key', 500);
            $table->ulid('selected_scan_id')->nullable();
            $table->ulid('accepted_asset_id')->nullable();
            $table->ulid('created_by_membership_id');
            $table->ulid('decided_by_membership_id')->nullable();
            $table->string('decision_reason_key', 100)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('review_expires_at')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'candidate_asset_id'],
                'media_uploads_candidate_unique',
            );
            $table->unique(
                ['organization_id', 'accepted_asset_id'],
                'media_uploads_accepted_asset_unique',
            );
            $table->foreign(
                ['organization_id', 'created_by_membership_id'],
                'media_uploads_creator_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'decided_by_membership_id'],
                'media_uploads_decider_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'accepted_asset_id'],
                'media_uploads_accepted_asset_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'state', 'updated_at', 'id'],
                'media_uploads_state_index',
            );
            $table->index(
                ['organization_id', 'expires_at', 'state'],
                'media_uploads_expiry_index',
            );
        });

        Schema::create('media_upload_parts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_upload_id');
            $table->unsignedInteger('part_number');
            $table->unsignedBigInteger('byte_size');
            $table->char('part_hash', 64);
            $table->string('storage_object_key', 500);
            $table->ulid('received_by_membership_id');
            $table->timestampTz('received_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'media_upload_id', 'part_number'],
                'media_upload_parts_number_unique',
            );
            $table->foreign(
                ['organization_id', 'media_upload_id'],
                'media_upload_parts_upload_fk',
            )->references(['organization_id', 'id'])
                ->on('media_uploads')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'received_by_membership_id'],
                'media_upload_parts_receiver_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('media_security_scans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_upload_id')->nullable();
            $table->ulid('media_asset_id')->nullable();
            $table->string('asset_kind', 48);
            $table->char('object_hash', 64);
            $table->unsignedBigInteger('byte_size');
            $table->string('inspection_outcome', 32);
            $table->string('malware_outcome', 32);
            $table->string('detected_mime_type', 120)->nullable();
            $table->unsignedBigInteger('duration_milliseconds')->nullable();
            $table->string('inspector_name', 100);
            $table->string('inspector_policy_version', 100);
            $table->string('scanner_name', 100);
            $table->string('scanner_engine_version', 100)->nullable();
            $table->string('scanner_policy_version', 100);
            $table->char('technical_metadata_hash', 64);
            $table->timestampTz('inspected_at');
            $table->timestampTz('scanned_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'id', 'object_hash'],
                'media_security_scans_id_hash_unique',
            );
            $table->foreign(
                ['organization_id', 'media_upload_id'],
                'media_security_scans_upload_fk',
            )->references(['organization_id', 'id'])
                ->on('media_uploads')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id'],
                'media_security_scans_asset_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'media_upload_id', 'created_at', 'id'],
                'media_security_scans_upload_index',
            );
            $table->index(
                ['organization_id', 'media_asset_id', 'created_at', 'id'],
                'media_security_scans_asset_index',
            );
        });

        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->foreign(
                ['organization_id', 'selected_scan_id'],
                'media_uploads_selected_scan_fk',
            )->references(['organization_id', 'id'])
                ->on('media_security_scans')
                ->restrictOnDelete();
        });

        Schema::create('media_upload_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_upload_id');
            $table->string('event_type', 64);
            $table->unsignedInteger('upload_version');
            $table->ulid('media_security_scan_id')->nullable();
            $table->ulid('media_asset_id')->nullable();
            $table->ulid('actor_membership_id')->nullable();
            $table->string('reason_key', 100)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->foreign(
                ['organization_id', 'media_upload_id'],
                'media_upload_events_upload_fk',
            )->references(['organization_id', 'id'])
                ->on('media_uploads')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_security_scan_id'],
                'media_upload_events_scan_fk',
            )->references(['organization_id', 'id'])
                ->on('media_security_scans')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id'],
                'media_upload_events_asset_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'media_upload_events_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'media_upload_id', 'occurred_at', 'id'],
                'media_upload_events_timeline_index',
            );
        });

        Schema::create('media_asset_lifecycles', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_asset_id');
            $table->string('asset_kind', 48);
            $table->string('state', 24)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->ulid('selected_scan_id');
            $table->ulid('predecessor_media_asset_id')->nullable();
            $table->ulid('managed_by_membership_id');
            $table->ulid('retired_by_membership_id')->nullable();
            $table->string('retirement_reason_key', 100)->nullable();
            $table->timestampTz('managed_at');
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'media_asset_id'],
                'media_asset_lifecycles_primary',
            );
            $table->unique(
                ['organization_id', 'media_asset_id', 'selected_scan_id'],
                'media_asset_lifecycles_scan_unique',
            );
            $table->foreign(
                ['organization_id', 'media_asset_id'],
                'media_asset_lifecycles_asset_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'selected_scan_id'],
                'media_asset_lifecycles_scan_fk',
            )->references(['organization_id', 'id'])
                ->on('media_security_scans')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'predecessor_media_asset_id'],
                'media_asset_lifecycles_predecessor_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'managed_by_membership_id'],
                'media_asset_lifecycles_manager_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'retired_by_membership_id'],
                'media_asset_lifecycles_retirer_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'state', 'updated_at', 'media_asset_id'],
                'media_asset_lifecycles_state_index',
            );
        });

        Schema::create('media_asset_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_asset_id');
            $table->string('event_type', 64);
            $table->unsignedInteger('lifecycle_version');
            $table->ulid('selected_scan_id')->nullable();
            $table->ulid('related_media_asset_id')->nullable();
            $table->ulid('media_upload_id')->nullable();
            $table->ulid('actor_membership_id');
            $table->string('reason_key', 100)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->foreign(
                ['organization_id', 'media_asset_id'],
                'media_asset_events_asset_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'selected_scan_id'],
                'media_asset_events_scan_fk',
            )->references(['organization_id', 'id'])
                ->on('media_security_scans')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'related_media_asset_id'],
                'media_asset_events_related_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_upload_id'],
                'media_asset_events_upload_fk',
            )->references(['organization_id', 'id'])
                ->on('media_uploads')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'media_asset_events_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'media_asset_id', 'occurred_at', 'id'],
                'media_asset_events_timeline_index',
            );
        });

        Schema::create('media_asset_metadata_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_asset_id');
            $table->string('asset_kind', 48);
            $table->ulid('parent_revision_id')->nullable();
            $table->unsignedInteger('revision_number');
            $table->string('operator_label', 160);
            $table->text('transcript');
            $table->text('text_alternative')->nullable();
            $table->char('content_hash', 64);
            $table->ulid('authored_by_membership_id');
            $table->timestampTz('authored_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'media_asset_id', 'id'],
                'media_metadata_revisions_asset_id_unique',
            );
            $table->unique(
                ['organization_id', 'media_asset_id', 'revision_number'],
                'media_metadata_revisions_number_unique',
            );
            $table->unique(
                ['organization_id', 'media_asset_id', 'id', 'content_hash'],
                'media_metadata_revisions_hash_unique',
            );
            $table->foreign(
                ['organization_id', 'media_asset_id'],
                'media_metadata_revisions_asset_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id', 'parent_revision_id'],
                'media_metadata_revisions_parent_fk',
            )->references(['organization_id', 'media_asset_id', 'id'])
                ->on('media_asset_metadata_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'authored_by_membership_id'],
                'media_metadata_revisions_author_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'media_asset_id', 'authored_at', 'id'],
                'media_metadata_revisions_timeline_index',
            );
        });

        Schema::create('media_asset_metadata_heads', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_asset_id');
            $table->ulid('metadata_revision_id');
            $table->unsignedInteger('version');
            $table->ulid('updated_by_membership_id');
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'media_asset_id'],
                'media_asset_metadata_heads_primary',
            );
            $table->foreign(
                ['organization_id', 'media_asset_id', 'metadata_revision_id'],
                'media_metadata_heads_revision_fk',
            )->references(['organization_id', 'media_asset_id', 'id'])
                ->on('media_asset_metadata_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'updated_by_membership_id'],
                'media_metadata_heads_updater_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('media_asset_metadata_variants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_asset_id');
            $table->ulid('metadata_revision_id');
            $table->string('asset_kind', 48);
            $table->string('locale', 16)->default('kn-IN');
            $table->ulid('parent_variant_id')->nullable();
            $table->unsignedInteger('revision_number');
            $table->char('source_content_hash', 64);
            $table->text('transcript');
            $table->text('text_alternative')->nullable();
            $table->char('content_hash', 64);
            $table->ulid('translated_by_membership_id');
            $table->timestampTz('translated_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'metadata_revision_id', 'id'],
                'media_metadata_variants_revision_id_unique',
            );
            $table->unique(
                ['organization_id', 'metadata_revision_id', 'revision_number'],
                'media_metadata_variants_number_unique',
            );
            $table->unique(
                ['organization_id', 'metadata_revision_id', 'id', 'content_hash'],
                'media_metadata_variants_hash_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'media_asset_id',
                    'metadata_revision_id',
                    'source_content_hash',
                ],
                'media_metadata_variants_source_fk',
            )->references([
                'organization_id',
                'media_asset_id',
                'id',
                'content_hash',
            ])->on('media_asset_metadata_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'metadata_revision_id', 'parent_variant_id'],
                'media_metadata_variants_parent_fk',
            )->references(['organization_id', 'metadata_revision_id', 'id'])
                ->on('media_asset_metadata_variants')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'translated_by_membership_id'],
                'media_metadata_variants_translator_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('media_asset_metadata_variant_heads', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('metadata_revision_id');
            $table->ulid('metadata_variant_id');
            $table->unsignedInteger('version');
            $table->ulid('updated_by_membership_id');
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'metadata_revision_id'],
                'media_metadata_variant_heads_primary',
            );
            $table->foreign(
                ['organization_id', 'metadata_revision_id', 'metadata_variant_id'],
                'media_metadata_variant_heads_variant_fk',
            )->references(['organization_id', 'metadata_revision_id', 'id'])
                ->on('media_asset_metadata_variants')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'updated_by_membership_id'],
                'media_metadata_variant_heads_updater_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('media_asset_metadata_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_asset_id');
            $table->ulid('program_id');
            $table->ulid('metadata_revision_id');
            $table->char('source_content_hash', 64);
            $table->string('review_kind', 24);
            $table->ulid('metadata_variant_id')->nullable();
            $table->char('variant_content_hash', 64)->nullable();
            $table->string('decision', 24);
            $table->string('rationale_key', 100)->nullable();
            $table->ulid('authority_appointment_id');
            $table->ulid('authority_appointment_event_id');
            $table->ulid('reviewer_membership_id');
            $table->timestampTz('reviewed_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'program_id',
                    'media_asset_id',
                    'metadata_revision_id',
                    'id',
                    'decision',
                ],
                'media_metadata_reviews_evidence_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id'],
                'media_metadata_reviews_program_fk',
            )->references(['organization_id', 'id'])
                ->on('programs')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'media_asset_id',
                    'metadata_revision_id',
                    'source_content_hash',
                ],
                'media_metadata_reviews_source_fk',
            )->references([
                'organization_id',
                'media_asset_id',
                'id',
                'content_hash',
            ])->on('media_asset_metadata_revisions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'metadata_revision_id',
                    'metadata_variant_id',
                    'variant_content_hash',
                ],
                'media_metadata_reviews_variant_fk',
            )->references([
                'organization_id',
                'metadata_revision_id',
                'id',
                'content_hash',
            ])->on('media_asset_metadata_variants')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'authority_appointment_id',
                    'reviewer_membership_id',
                ],
                'media_metadata_reviews_reviewer_fk',
            )->references([
                'organization_id',
                'id',
                'appointee_membership_id',
            ])->on('authority_appointments')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'authority_appointment_id',
                    'authority_appointment_event_id',
                ],
                'media_metadata_reviews_appointment_event_fk',
            )->references([
                'organization_id',
                'authority_appointment_id',
                'id',
            ])->on('authority_appointment_events')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'program_id', 'metadata_revision_id', 'review_kind', 'reviewed_at', 'id'],
                'media_metadata_reviews_timeline_index',
            );
        });

        Schema::create('media_asset_replacements', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('predecessor_media_asset_id');
            $table->ulid('successor_media_asset_id');
            $table->ulid('recorded_by_membership_id');
            $table->timestampTz('recorded_at');
            $table->timestampTz('created_at');

            $table->primary(
                [
                    'organization_id',
                    'predecessor_media_asset_id',
                    'successor_media_asset_id',
                ],
                'media_asset_replacements_primary',
            );
            $table->unique(
                ['organization_id', 'successor_media_asset_id'],
                'media_asset_replacements_successor_unique',
            );
            $table->foreign(
                ['organization_id', 'predecessor_media_asset_id'],
                'media_asset_replacements_predecessor_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'successor_media_asset_id'],
                'media_asset_replacements_successor_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'recorded_by_membership_id'],
                'media_asset_replacements_recorder_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::table('content_revision_media_assets', function (Blueprint $table): void {
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_revision_id',
                    'usage_key',
                ],
                'content_revision_media_usage_unique',
            );
        });

        Schema::create('media_attachment_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('source_revision_id');
            $table->ulid('media_asset_id');
            $table->string('usage_key', 80);
            $table->unsignedInteger('previous_workspace_version');
            $table->unsignedInteger('successor_workspace_version');
            $table->ulid('selected_scan_id');
            $table->ulid('metadata_revision_id');
            $table->ulid('english_review_id');
            $table->ulid('metadata_variant_id');
            $table->ulid('kannada_review_id');
            $table->ulid('media_rights_revision_id');
            $table->ulid('attached_by_membership_id');
            $table->timestampTz('attached_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'source_revision_id',
                    'usage_key',
                ],
                'media_attachment_events_usage_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id', 'curriculum_version_id'],
                'media_attachment_events_workspace_fk',
            )->references(['organization_id', 'program_id', 'curriculum_version_id'])
                ->on('curriculum_workspaces')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'source_revision_id'],
                'media_attachment_events_source_fk',
            )->references(['organization_id', 'curriculum_version_id', 'id'])
                ->on('content_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id'],
                'media_attachment_events_asset_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'selected_scan_id'],
                'media_attachment_events_scan_fk',
            )->references(['organization_id', 'id'])
                ->on('media_security_scans')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'metadata_revision_id'],
                'media_attachment_events_metadata_fk',
            )->references(['organization_id', 'id'])
                ->on('media_asset_metadata_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'english_review_id'],
                'media_attachment_events_en_review_fk',
            )->references(['organization_id', 'id'])
                ->on('media_asset_metadata_reviews')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'metadata_variant_id'],
                'media_attachment_events_variant_fk',
            )->references(['organization_id', 'id'])
                ->on('media_asset_metadata_variants')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'kannada_review_id'],
                'media_attachment_events_kn_review_fk',
            )->references(['organization_id', 'id'])
                ->on('media_asset_metadata_reviews')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_rights_revision_id'],
                'media_attachment_events_rights_fk',
            )->references(['organization_id', 'id'])
                ->on('media_rights_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'attached_by_membership_id'],
                'media_attachment_events_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('media_object_deletion_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('media_upload_id')->nullable();
            $table->string('storage_object_key', 500);
            $table->char('object_hash', 64)->nullable();
            $table->string('reason_key', 100);
            $table->string('state', 24)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error_key', 100)->nullable();
            $table->timestampTz('due_at');
            $table->timestampTz('last_attempted_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'storage_object_key'],
                'media_object_deletion_requests_object_unique',
            );
            $table->foreign(
                ['organization_id', 'media_upload_id'],
                'media_object_deletion_requests_upload_fk',
            )->references(['organization_id', 'id'])
                ->on('media_uploads')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'state', 'due_at', 'id'],
                'media_object_deletion_requests_due_index',
            );
        });

        Schema::create('publication_media_governance_components', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->ulid('source_revision_id');
            $table->ulid('media_asset_id');
            $table->string('usage_key', 80);
            $table->ulid('selected_scan_id');
            $table->char('object_hash', 64);
            $table->string('inspector_policy_version', 100);
            $table->string('scanner_policy_version', 100);
            $table->ulid('metadata_revision_id');
            $table->char('metadata_content_hash', 64);
            $table->ulid('english_review_id');
            $table->ulid('metadata_variant_id');
            $table->char('variant_content_hash', 64);
            $table->ulid('kannada_review_id');
            $table->ulid('media_rights_revision_id');
            $table->string('rights_evidence_status', 24)
                ->default('approved');
            $table->char('rights_evidence_hash', 64);
            $table->timestampTz('created_at');

            $table->primary(
                [
                    'organization_id',
                    'curriculum_publication_id',
                    'source_revision_id',
                    'media_asset_id',
                    'usage_key',
                ],
                'publication_media_governance_primary',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_publication_id',
                    'source_revision_id',
                    'media_asset_id',
                    'usage_key',
                ],
                'publication_media_governance_media_fk',
            )->references([
                'organization_id',
                'curriculum_publication_id',
                'source_revision_id',
                'media_asset_id',
                'usage_key',
            ])->on('publication_media_components')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'selected_scan_id', 'object_hash'],
                'publication_media_governance_scan_fk',
            )->references(['organization_id', 'id', 'object_hash'])
                ->on('media_security_scans')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'media_asset_id',
                    'metadata_revision_id',
                    'metadata_content_hash',
                ],
                'publication_media_governance_metadata_fk',
            )->references([
                'organization_id',
                'media_asset_id',
                'id',
                'content_hash',
            ])->on('media_asset_metadata_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'english_review_id'],
                'publication_media_governance_en_review_fk',
            )->references(['organization_id', 'id'])
                ->on('media_asset_metadata_reviews')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'metadata_revision_id',
                    'metadata_variant_id',
                    'variant_content_hash',
                ],
                'publication_media_governance_variant_fk',
            )->references([
                'organization_id',
                'metadata_revision_id',
                'id',
                'content_hash',
            ])->on('media_asset_metadata_variants')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'kannada_review_id'],
                'publication_media_governance_kn_review_fk',
            )->references(['organization_id', 'id'])
                ->on('media_asset_metadata_reviews')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'media_asset_id',
                    'media_rights_revision_id',
                    'rights_evidence_status',
                    'rights_evidence_hash',
                    'object_hash',
                ],
                'publication_media_governance_rights_fk',
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
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->dropPostgresConstraints();
        }

        Schema::dropIfExists('publication_media_governance_components');
        Schema::dropIfExists('media_object_deletion_requests');
        Schema::dropIfExists('media_attachment_events');
        Schema::table('content_revision_media_assets', function (Blueprint $table): void {
            $table->dropUnique('content_revision_media_usage_unique');
        });
        Schema::dropIfExists('media_asset_replacements');
        Schema::dropIfExists('media_asset_metadata_reviews');
        Schema::dropIfExists('media_asset_metadata_variant_heads');
        Schema::dropIfExists('media_asset_metadata_variants');
        Schema::dropIfExists('media_asset_metadata_heads');
        Schema::dropIfExists('media_asset_metadata_revisions');
        Schema::dropIfExists('media_asset_events');
        Schema::dropIfExists('media_asset_lifecycles');
        Schema::dropIfExists('media_upload_events');
        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::table('media_uploads', function (Blueprint $table): void {
                $table->dropForeign('media_uploads_selected_scan_fk');
            });
        }
        Schema::dropIfExists('media_security_scans');
        Schema::dropIfExists('media_upload_parts');
        Schema::dropIfExists('media_uploads');
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
ALTER TABLE media_uploads
ADD CONSTRAINT media_uploads_values_check
CHECK (
    asset_kind IN ('model_audio', 'lesson_video')
    AND state IN ('uploading', 'finalized', 'scan_clean', 'accepted', 'rejected', 'expired', 'inspection_failed', 'scan_failed', 'promotion_failed')
    AND version > 0
    AND total_bytes > 0
    AND whole_object_hash ~ '^[0-9a-f]{64}$'
    AND part_size_bytes > 0
    AND expected_part_count > 0
    AND received_part_count <= expected_part_count
    AND accepted_object_key <> ''
    AND expires_at > created_at
    AND (decision_reason_key IS NULL OR decision_reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND ((state = 'accepted') = (accepted_asset_id IS NOT NULL))
    AND (
        ((decided_at IS NULL) = (decided_by_membership_id IS NULL))
        OR (state = 'expired' AND decided_at IS NOT NULL AND decided_by_membership_id IS NULL)
    )
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_upload_parts
ADD CONSTRAINT media_upload_parts_values_check
CHECK (
    part_number > 0
    AND byte_size > 0
    AND part_hash ~ '^[0-9a-f]{64}$'
    AND storage_object_key <> ''
    AND received_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_security_scans
ADD CONSTRAINT media_security_scans_values_check
CHECK (
    ((media_upload_id IS NOT NULL)::int + (media_asset_id IS NOT NULL)::int) = 1
    AND asset_kind IN ('model_audio', 'lesson_video')
    AND object_hash ~ '^[0-9a-f]{64}$'
    AND byte_size > 0
    AND inspection_outcome IN ('passed', 'failed', 'unavailable')
    AND malware_outcome IN ('clean', 'infected', 'error', 'unavailable')
    AND technical_metadata_hash ~ '^[0-9a-f]{64}$'
    AND inspected_at <= scanned_at
    AND scanned_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_upload_events
ADD CONSTRAINT media_upload_events_values_check
CHECK (
    event_type IN ('upload.started', 'part.received', 'upload.finalized', 'scan.clean', 'scan.failed', 'inspection.failed', 'quarantine.accepted', 'quarantine.rejected', 'upload.expired', 'promotion.failed')
    AND upload_version > 0
    AND (reason_key IS NULL OR reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND occurred_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_lifecycles
ADD CONSTRAINT media_asset_lifecycles_values_check
CHECK (
    asset_kind IN ('model_audio', 'lesson_video')
    AND state IN ('active', 'retired')
    AND version > 0
    AND ((state = 'retired') = (retired_at IS NOT NULL))
    AND ((retired_at IS NULL) = (retired_by_membership_id IS NULL))
    AND (retirement_reason_key IS NULL OR retirement_reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND predecessor_media_asset_id IS DISTINCT FROM media_asset_id
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_events
ADD CONSTRAINT media_asset_events_values_check
CHECK (
    event_type IN ('asset.accepted', 'asset.adopted', 'asset.replacement_recorded', 'asset.retired')
    AND lifecycle_version > 0
    AND (reason_key IS NULL OR reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND occurred_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_metadata_revisions
ADD CONSTRAINT media_metadata_revisions_values_check
CHECK (
    asset_kind IN ('model_audio', 'lesson_video')
    AND revision_number > 0
    AND content_hash ~ '^[0-9a-f]{64}$'
    AND operator_label <> ''
    AND transcript <> ''
    AND ((asset_kind = 'model_audio' AND text_alternative IS NULL) OR (asset_kind = 'lesson_video' AND text_alternative IS NOT NULL AND text_alternative <> ''))
    AND ((revision_number = 1 AND parent_revision_id IS NULL) OR (revision_number > 1 AND parent_revision_id IS NOT NULL))
    AND authored_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_metadata_heads
ADD CONSTRAINT media_metadata_heads_values_check
CHECK (version > 0)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_metadata_variants
ADD CONSTRAINT media_metadata_variants_values_check
CHECK (
    asset_kind IN ('model_audio', 'lesson_video')
    AND locale = 'kn-IN'
    AND revision_number > 0
    AND source_content_hash ~ '^[0-9a-f]{64}$'
    AND content_hash ~ '^[0-9a-f]{64}$'
    AND transcript <> ''
    AND ((asset_kind = 'model_audio' AND text_alternative IS NULL) OR (asset_kind = 'lesson_video' AND text_alternative IS NOT NULL AND text_alternative <> ''))
    AND ((revision_number = 1 AND parent_variant_id IS NULL) OR (revision_number > 1 AND parent_variant_id IS NOT NULL))
    AND translated_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_metadata_variant_heads
ADD CONSTRAINT media_metadata_variant_heads_values_check
CHECK (version > 0)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_metadata_reviews
ADD CONSTRAINT media_metadata_reviews_values_check
CHECK (
    source_content_hash ~ '^[0-9a-f]{64}$'
    AND review_kind IN ('academic_en', 'kannada')
    AND decision IN ('approved', 'rejected', 'withdrawn')
    AND (rationale_key IS NULL OR rationale_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND (
        (review_kind = 'academic_en' AND metadata_variant_id IS NULL AND variant_content_hash IS NULL)
        OR
        (review_kind = 'kannada' AND metadata_variant_id IS NOT NULL AND variant_content_hash ~ '^[0-9a-f]{64}$')
    )
    AND reviewed_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_asset_replacements
ADD CONSTRAINT media_asset_replacements_values_check
CHECK (
    predecessor_media_asset_id <> successor_media_asset_id
    AND recorded_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_attachment_events
ADD CONSTRAINT media_attachment_events_values_check
CHECK (
    usage_key IN ('model_audio', 'lesson_video')
    AND previous_workspace_version > 0
    AND successor_workspace_version = previous_workspace_version + 1
    AND attached_at = created_at
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE media_object_deletion_requests
ADD CONSTRAINT media_object_deletion_requests_values_check
CHECK (
    state IN ('pending', 'retrying', 'completed', 'dead_letter')
    AND attempt_count >= 0
    AND reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$'
    AND (last_error_key IS NULL OR last_error_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND (object_hash IS NULL OR object_hash ~ '^[0-9a-f]{64}$')
    AND ((state = 'completed') = (completed_at IS NOT NULL))
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE publication_media_governance_components
ADD CONSTRAINT publication_media_governance_values_check
CHECK (
    usage_key IN ('model_audio', 'lesson_video')
    AND object_hash ~ '^[0-9a-f]{64}$'
    AND metadata_content_hash ~ '^[0-9a-f]{64}$'
    AND variant_content_hash ~ '^[0-9a-f]{64}$'
    AND rights_evidence_status = 'approved'
    AND rights_evidence_hash ~ '^[0-9a-f]{64}$'
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION admin_media_reject_history_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'admin media evidence is append-only';
END;
$$;

CREATE TRIGGER media_upload_parts_append_only
BEFORE UPDATE OR DELETE ON media_upload_parts
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_security_scans_append_only
BEFORE UPDATE OR DELETE ON media_security_scans
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_upload_events_append_only
BEFORE UPDATE OR DELETE ON media_upload_events
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_asset_events_append_only
BEFORE UPDATE OR DELETE ON media_asset_events
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_metadata_revisions_append_only
BEFORE UPDATE OR DELETE ON media_asset_metadata_revisions
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_metadata_variants_append_only
BEFORE UPDATE OR DELETE ON media_asset_metadata_variants
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_metadata_reviews_append_only
BEFORE UPDATE OR DELETE ON media_asset_metadata_reviews
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_asset_replacements_append_only
BEFORE UPDATE OR DELETE ON media_asset_replacements
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_attachment_events_append_only
BEFORE UPDATE OR DELETE ON media_attachment_events
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER publication_media_governance_append_only
BEFORE UPDATE OR DELETE ON publication_media_governance_components
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE OR REPLACE FUNCTION admin_media_upload_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.candidate_asset_id IS DISTINCT FROM NEW.candidate_asset_id
        OR OLD.asset_kind IS DISTINCT FROM NEW.asset_kind
        OR OLD.safe_label IS DISTINCT FROM NEW.safe_label
        OR OLD.total_bytes IS DISTINCT FROM NEW.total_bytes
        OR OLD.whole_object_hash IS DISTINCT FROM NEW.whole_object_hash
        OR OLD.part_size_bytes IS DISTINCT FROM NEW.part_size_bytes
        OR OLD.expected_part_count IS DISTINCT FROM NEW.expected_part_count
        OR OLD.accepted_object_key IS DISTINCT FROM NEW.accepted_object_key
        OR OLD.created_by_membership_id IS DISTINCT FROM NEW.created_by_membership_id
        OR OLD.expires_at IS DISTINCT FROM NEW.expires_at
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR NEW.version <> OLD.version + 1
        OR OLD.state IN ('accepted', 'rejected', 'expired')
        OR (OLD.state = 'uploading' AND NEW.state NOT IN ('uploading', 'finalized', 'expired'))
        OR (OLD.state = 'finalized' AND NEW.state NOT IN ('scan_clean', 'inspection_failed', 'scan_failed'))
        OR (OLD.state = 'scan_clean' AND NEW.state NOT IN ('accepted', 'rejected', 'expired', 'promotion_failed'))
        OR (OLD.state = 'promotion_failed' AND NEW.state NOT IN ('accepted', 'rejected', 'expired', 'promotion_failed'))
        OR (OLD.state IN ('inspection_failed', 'scan_failed') AND NEW.state NOT IN ('rejected', 'expired'))
    THEN
        RAISE EXCEPTION 'invalid media upload transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER media_uploads_transition
BEFORE UPDATE ON media_uploads
FOR EACH ROW EXECUTE FUNCTION admin_media_upload_transition();

CREATE TRIGGER media_uploads_no_delete
BEFORE DELETE ON media_uploads
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE OR REPLACE FUNCTION admin_media_lifecycle_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.media_asset_id IS DISTINCT FROM NEW.media_asset_id
        OR OLD.asset_kind IS DISTINCT FROM NEW.asset_kind
        OR OLD.selected_scan_id IS DISTINCT FROM NEW.selected_scan_id
        OR OLD.managed_by_membership_id IS DISTINCT FROM NEW.managed_by_membership_id
        OR OLD.managed_at IS DISTINCT FROM NEW.managed_at
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR NEW.version <> OLD.version + 1
        OR OLD.state <> 'active'
        OR (
            NEW.state = 'active'
            AND NOT (
                OLD.predecessor_media_asset_id IS NULL
                AND NEW.predecessor_media_asset_id IS NOT NULL
                AND NEW.retired_at IS NULL
                AND NEW.retired_by_membership_id IS NULL
            )
        )
        OR NEW.state NOT IN ('active', 'retired')
    THEN
        RAISE EXCEPTION 'invalid media asset lifecycle transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER media_asset_lifecycles_transition
BEFORE UPDATE ON media_asset_lifecycles
FOR EACH ROW EXECUTE FUNCTION admin_media_lifecycle_transition();

CREATE TRIGGER media_asset_lifecycles_no_delete
BEFORE DELETE ON media_asset_lifecycles
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE OR REPLACE FUNCTION admin_media_head_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR NEW.version <> OLD.version + 1
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
    THEN
        RAISE EXCEPTION 'invalid media metadata head transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER media_metadata_heads_transition
BEFORE UPDATE ON media_asset_metadata_heads
FOR EACH ROW EXECUTE FUNCTION admin_media_head_transition();

CREATE TRIGGER media_metadata_heads_no_delete
BEFORE DELETE ON media_asset_metadata_heads
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_metadata_variant_heads_transition
BEFORE UPDATE ON media_asset_metadata_variant_heads
FOR EACH ROW EXECUTE FUNCTION admin_media_head_transition();

CREATE TRIGGER media_metadata_variant_heads_no_delete
BEFORE DELETE ON media_asset_metadata_variant_heads
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();

CREATE TRIGGER media_object_deletion_requests_no_delete
BEFORE DELETE ON media_object_deletion_requests
FOR EACH ROW EXECUTE FUNCTION admin_media_reject_history_mutation();
SQL);
    }

    private function dropPostgresConstraints(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS media_object_deletion_requests_no_delete ON media_object_deletion_requests;
DROP TRIGGER IF EXISTS media_metadata_variant_heads_no_delete ON media_asset_metadata_variant_heads;
DROP TRIGGER IF EXISTS media_metadata_variant_heads_transition ON media_asset_metadata_variant_heads;
DROP TRIGGER IF EXISTS media_metadata_heads_no_delete ON media_asset_metadata_heads;
DROP TRIGGER IF EXISTS media_metadata_heads_transition ON media_asset_metadata_heads;
DROP TRIGGER IF EXISTS media_asset_lifecycles_no_delete ON media_asset_lifecycles;
DROP TRIGGER IF EXISTS media_asset_lifecycles_transition ON media_asset_lifecycles;
DROP TRIGGER IF EXISTS media_uploads_no_delete ON media_uploads;
DROP TRIGGER IF EXISTS media_uploads_transition ON media_uploads;
DROP TRIGGER IF EXISTS publication_media_governance_append_only ON publication_media_governance_components;
DROP TRIGGER IF EXISTS media_attachment_events_append_only ON media_attachment_events;
DROP TRIGGER IF EXISTS media_asset_replacements_append_only ON media_asset_replacements;
DROP TRIGGER IF EXISTS media_metadata_reviews_append_only ON media_asset_metadata_reviews;
DROP TRIGGER IF EXISTS media_metadata_variants_append_only ON media_asset_metadata_variants;
DROP TRIGGER IF EXISTS media_metadata_revisions_append_only ON media_asset_metadata_revisions;
DROP TRIGGER IF EXISTS media_asset_events_append_only ON media_asset_events;
DROP TRIGGER IF EXISTS media_upload_events_append_only ON media_upload_events;
DROP TRIGGER IF EXISTS media_security_scans_append_only ON media_security_scans;
DROP TRIGGER IF EXISTS media_upload_parts_append_only ON media_upload_parts;
DROP FUNCTION IF EXISTS admin_media_head_transition();
DROP FUNCTION IF EXISTS admin_media_lifecycle_transition();
DROP FUNCTION IF EXISTS admin_media_upload_transition();
DROP FUNCTION IF EXISTS admin_media_reject_history_mutation();
SQL);
    }
};
