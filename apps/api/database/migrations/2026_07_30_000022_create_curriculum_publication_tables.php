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
        Schema::table('audit_events', function (Blueprint $table): void {
            /*
             * Existing platform audit history predates this migration, so the
             * column remains nullable. Every newly recorded signed platform
             * operation supplies a nonce, and the unique index makes that
             * authorization consumable only once.
             */
            $table->uuid('authorization_nonce')->nullable();
            $table->unique(
                'authorization_nonce',
                'audit_events_authorization_nonce_unique',
            );
        });

        Schema::create('curriculum_publications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->unsignedInteger('publication_number');
            $table->unsignedSmallInteger('manifest_schema_version')->default(1);
            $table->json('manifest');
            $table->char('manifest_hash', 64);
            $table->ulid('created_by_membership_id');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'program_id', 'id'],
                'curriculum_publications_program_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'program_id',
                    'curriculum_version_id',
                    'id',
                ],
                'curriculum_publications_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'curriculum_publications_enrollment_tuple_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'program_id',
                    'publication_number',
                ],
                'curriculum_publications_program_number_unique',
            );
            $table->unique(
                ['organization_id', 'program_id', 'manifest_hash'],
                'curriculum_publications_manifest_hash_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id', 'curriculum_version_id'],
                'curriculum_publications_version_tenant_fk',
            )->references(['organization_id', 'program_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'created_by_membership_id'],
                'curriculum_publications_creator_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('publication_graph_components', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->ulid('curriculum_graph_revision_id');
            $table->char('graph_hash', 64);
            $table->ulid('curriculum_graph_review_id');
            $table->string('graph_review_decision', 24)
                ->default('approved');
            $table->ulid('academic_appointment_id');
            $table->ulid('academic_appointment_event_id');
            $table->ulid('reviewer_membership_id');
            $table->timestampTz('created_at');

            $table->primary(
                ['organization_id', 'curriculum_publication_id'],
                'publication_graph_components_primary',
            );
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'publication_graph_components_publication_fk',
            )->references([
                'organization_id',
                'program_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_graph_revision_id',
                    'graph_hash',
                ],
                'publication_graph_components_revision_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'graph_hash',
            ])->on('curriculum_graph_revisions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_graph_review_id',
                    'curriculum_graph_revision_id',
                    'graph_hash',
                    'graph_review_decision',
                    'academic_appointment_id',
                    'academic_appointment_event_id',
                    'reviewer_membership_id',
                ],
                'publication_graph_components_review_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'curriculum_graph_revision_id',
                'graph_hash',
                'decision',
                'authority_appointment_id',
                'authority_appointment_event_id',
                'reviewer_membership_id',
            ])->on('curriculum_graph_reviews')
                ->restrictOnDelete();
        });

        Schema::create('publication_content_components', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->ulid('content_document_id');
            $table->ulid('source_revision_id');
            $table->char('source_content_hash', 64);
            $table->ulid('academic_review_id');
            $table->string('academic_review_kind', 32)
                ->default('academic_en');
            $table->string('academic_review_decision', 24)
                ->default('approved');
            $table->ulid('academic_appointment_id');
            $table->ulid('academic_appointment_event_id');
            $table->ulid('localized_variant_id')->nullable();
            $table->char('localized_content_hash', 64)->nullable();
            $table->ulid('kannada_review_id')->nullable();
            $table->string('kannada_review_kind', 32)->nullable();
            $table->string('kannada_review_decision', 24)->nullable();
            $table->ulid('kannada_appointment_id')->nullable();
            $table->ulid('kannada_appointment_event_id')->nullable();
            $table->timestampTz('created_at');

            $table->primary(
                [
                    'organization_id',
                    'curriculum_publication_id',
                    'content_document_id',
                ],
                'publication_content_components_primary',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_publication_id',
                    'content_document_id',
                    'source_revision_id',
                ],
                'publication_content_components_source_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'publication_content_components_publication_fk',
            )->references([
                'organization_id',
                'program_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                ],
                'publication_content_components_document_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('content_documents')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                    'source_revision_id',
                ],
                'publication_content_components_source_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'content_document_id',
                'id',
            ])->on('content_revisions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'source_revision_id',
                    'source_content_hash',
                ],
                'publication_content_components_source_hash_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'content_hash',
            ])->on('content_revisions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'academic_review_id',
                    'academic_review_kind',
                    'academic_review_decision',
                    'source_revision_id',
                    'source_content_hash',
                    'academic_appointment_id',
                    'academic_appointment_event_id',
                ],
                'publication_content_components_academic_review_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'review_kind',
                'decision',
                'source_revision_id',
                'source_content_hash',
                'authority_appointment_id',
                'authority_appointment_event_id',
            ])->on('content_reviews')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'localized_variant_id',
                    'source_revision_id',
                    'source_content_hash',
                    'localized_content_hash',
                ],
                'publication_content_components_variant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'source_revision_id',
                'source_content_hash',
                'content_hash',
            ])->on('localized_variants')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'kannada_review_id',
                    'kannada_review_kind',
                    'kannada_review_decision',
                    'source_revision_id',
                    'source_content_hash',
                    'localized_variant_id',
                    'localized_content_hash',
                    'kannada_appointment_id',
                    'kannada_appointment_event_id',
                ],
                'publication_content_components_kannada_review_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'review_kind',
                'decision',
                'source_revision_id',
                'source_content_hash',
                'localized_variant_id',
                'localized_content_hash',
                'authority_appointment_id',
                'authority_appointment_event_id',
            ])->on('content_reviews')
                ->restrictOnDelete();
        });

        Schema::create('publication_media_components', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->ulid('content_document_id');
            $table->ulid('source_revision_id');
            $table->ulid('media_asset_id');
            $table->char('asset_hash', 64);
            $table->ulid('media_rights_revision_id');
            $table->string('rights_evidence_status', 24)
                ->default('approved');
            $table->char('rights_evidence_hash', 64);
            $table->string('usage_key', 80);
            $table->unsignedInteger('sequence');
            $table->timestampTz('created_at');

            $table->primary(
                [
                    'organization_id',
                    'curriculum_publication_id',
                    'source_revision_id',
                    'media_asset_id',
                    'usage_key',
                ],
                'publication_media_components_primary',
            );
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'publication_media_components_publication_fk',
            )->references([
                'organization_id',
                'program_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_publication_id',
                    'content_document_id',
                    'source_revision_id',
                ],
                'publication_media_components_content_fk',
            )->references([
                'organization_id',
                'curriculum_publication_id',
                'content_document_id',
                'source_revision_id',
            ])->on('publication_content_components')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                    'source_revision_id',
                ],
                'publication_media_components_source_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'content_document_id',
                'id',
            ])->on('content_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id', 'asset_hash'],
                'publication_media_components_asset_fk',
            )->references(['organization_id', 'id', 'asset_hash'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'source_revision_id',
                    'media_asset_id',
                    'usage_key',
                ],
                'publication_media_components_content_media_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'content_revision_id',
                'media_asset_id',
                'usage_key',
            ])->on('content_revision_media_assets')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'media_asset_id',
                    'media_rights_revision_id',
                    'rights_evidence_status',
                    'rights_evidence_hash',
                    'asset_hash',
                ],
                'publication_media_components_rights_fk',
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

        Schema::create('publication_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->string('event_type', 40);
            $table->ulid('previous_curriculum_version_id')->nullable();
            $table->ulid('previous_publication_id')->nullable();
            $table->string('actor_type', 32)->default('owner_membership');
            $table->ulid('actor_membership_id')->nullable();
            $table->foreignUlid('platform_actor_user_id')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->ulid('authentication_session_id')->nullable();
            $table->ulid('platform_audit_event_id')->nullable();
            $table->string('reason_key', 100)->nullable();
            $table->string('remediation_reference', 160)->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'program_id', 'id'],
                'publication_events_program_id_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'publication_events_publication_tenant_fk',
            )->references([
                'organization_id',
                'program_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'previous_curriculum_version_id',
                    'previous_publication_id',
                ],
                'publication_events_previous_tenant_fk',
            )->references([
                'organization_id',
                'program_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'publication_events_actor_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign('authentication_session_id')
                ->references('id')
                ->on('authentication_sessions')
                ->restrictOnDelete();
            $table->foreign('platform_audit_event_id')
                ->references('id')
                ->on('audit_events')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'program_id', 'effective_at'],
                'publication_events_timeline_index',
            );
            $table->index(
                [
                    'organization_id',
                    'program_id',
                    'curriculum_publication_id',
                    'event_type',
                    'effective_at',
                ],
                'publication_events_publication_timeline_index',
            );
        });

        Schema::create('publication_heads', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('active_curriculum_version_id')->nullable();
            $table->ulid('active_publication_id')->nullable();
            $table->ulid('last_event_id')->nullable();
            $table->boolean('is_suspended')->default(false);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampTz('updated_at');

            $table->primary(
                ['organization_id', 'program_id'],
                'publication_heads_primary',
            );
            $table->foreign(
                ['organization_id', 'program_id'],
                'publication_heads_program_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('programs')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'active_curriculum_version_id',
                    'active_publication_id',
                ],
                'publication_heads_active_tenant_fk',
            )->references([
                'organization_id',
                'program_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'last_event_id',
                ],
                'publication_heads_event_tenant_fk',
            )->references([
                'organization_id',
                'program_id',
                'id',
            ])->on('publication_events')
                ->restrictOnDelete();
        });

        Schema::create('publication_commands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('operation', 48);
            $table->string('aggregate_type', 48);
            $table->ulid('aggregate_id');
            $table->string('idempotency_key', 128);
            $table->char('request_fingerprint', 64);
            $table->string('result_type', 48);
            $table->ulid('result_id');
            $table->string('actor_type', 32)->default('owner_membership');
            $table->ulid('actor_membership_id')->nullable();
            $table->foreignUlid('actor_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestampTz('completed_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'operation',
                    'aggregate_type',
                    'aggregate_id',
                    'idempotency_key',
                ],
                'publication_commands_idempotency_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'actor_user_id',
                    'actor_membership_id',
                ],
                'publication_commands_actor_tenant_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_share_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignUlid('recipient_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_publication_id');
            $table->boolean('allow_preview')->default(false);
            $table->boolean('allow_enrollment')->default(false);
            $table->boolean('allow_clone')->default(false);
            $table->timestampTz('effective_at');
            $table->timestampTz('expires_at')->nullable();
            $table->ulid('issued_by_membership_id');
            $table->timestampTz('issued_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'recipient_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'curriculum_share_grants_access_tuple_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'curriculum_share_grants_publication_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'issued_by_membership_id'],
                'curriculum_share_grants_issuer_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                [
                    'recipient_organization_id',
                    'curriculum_publication_id',
                    'effective_at',
                ],
                'curriculum_share_grants_recipient_index',
            );
        });

        Schema::create('curriculum_share_grant_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_share_grant_id');
            $table->string('event_type', 32);
            $table->ulid('actor_membership_id');
            $table->string('reason_key', 100)->nullable();
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_share_grant_id', 'id'],
                'curriculum_share_grant_events_grant_id_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_share_grant_id'],
                'curriculum_share_grant_events_grant_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_share_grants')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'curriculum_share_grant_events_actor_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                [
                    'organization_id',
                    'curriculum_share_grant_id',
                    'effective_at',
                ],
                'curriculum_share_grant_events_timeline_index',
            );
        });

        Schema::create('curriculum_share_grant_heads', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_share_grant_id');
            $table->string('status', 24);
            $table->ulid('last_event_id');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampTz('updated_at');

            $table->primary(
                ['organization_id', 'curriculum_share_grant_id'],
                'curriculum_share_grant_heads_primary',
            );
            $table->foreign(
                ['organization_id', 'curriculum_share_grant_id'],
                'curriculum_share_grant_heads_grant_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_share_grants')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_share_grant_id',
                    'last_event_id',
                ],
                'curriculum_share_grant_heads_event_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_share_grant_id',
                'id',
            ])->on('curriculum_share_grant_events')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_clones', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignUlid('source_owner_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('source_curriculum_version_id');
            $table->ulid('source_curriculum_publication_id');
            $table->ulid('source_share_grant_id');
            $table->ulid('cloned_curriculum_version_id');
            $table->ulid('cloned_by_membership_id');
            $table->timestampTz('cloned_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'cloned_curriculum_version_id'],
                'curriculum_clones_recipient_version_unique',
            );
            $table->foreign(
                ['organization_id', 'cloned_curriculum_version_id'],
                'curriculum_clones_recipient_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'cloned_by_membership_id'],
                'curriculum_clones_actor_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'source_owner_organization_id',
                    'source_curriculum_version_id',
                    'source_curriculum_publication_id',
                ],
                'curriculum_clones_source_publication_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'source_owner_organization_id',
                    'source_share_grant_id',
                    'organization_id',
                    'source_curriculum_version_id',
                    'source_curriculum_publication_id',
                ],
                'curriculum_clones_source_grant_tenant_fk',
            )->references([
                'organization_id',
                'id',
                'recipient_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
            ])->on('curriculum_share_grants')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE publication_events ADD CONSTRAINT publication_events_type_check CHECK (event_type IN ('scheduled', 'activated', 'retired', 'rollback_activated', 'suspended', 'resumed'))",
            );
            DB::statement(
                "ALTER TABLE publication_events ADD CONSTRAINT publication_events_actor_check CHECK ((actor_type = 'owner_membership' AND actor_membership_id IS NOT NULL AND platform_actor_user_id IS NULL) OR (actor_type = 'platform_user' AND actor_membership_id IS NULL AND platform_actor_user_id IS NOT NULL))",
            );
            DB::statement(
                "ALTER TABLE publication_events ADD CONSTRAINT publication_events_emergency_evidence_check CHECK ((event_type IN ('suspended', 'resumed') AND authentication_session_id IS NOT NULL AND reason_key IS NOT NULL AND reason_key <> '' AND remediation_reference IS NOT NULL AND remediation_reference <> '' AND ((actor_type = 'owner_membership' AND platform_audit_event_id IS NULL) OR (actor_type = 'platform_user' AND platform_audit_event_id IS NOT NULL))) OR (event_type NOT IN ('suspended', 'resumed') AND authentication_session_id IS NULL AND platform_audit_event_id IS NULL AND remediation_reference IS NULL))",
            );
            DB::statement(
                'ALTER TABLE publication_events ADD CONSTRAINT publication_events_previous_pair_check CHECK ((previous_curriculum_version_id IS NULL AND previous_publication_id IS NULL) OR (previous_curriculum_version_id IS NOT NULL AND previous_publication_id IS NOT NULL))',
            );
            DB::statement(
                "ALTER TABLE publication_content_components ADD CONSTRAINT publication_content_components_academic_check CHECK (academic_review_kind = 'academic_en' AND academic_review_decision = 'approved')",
            );
            DB::statement(
                "ALTER TABLE publication_graph_components ADD CONSTRAINT publication_graph_components_review_check CHECK (graph_review_decision = 'approved')",
            );
            DB::statement(
                "ALTER TABLE publication_content_components ADD CONSTRAINT publication_content_components_kannada_check CHECK ((localized_variant_id IS NULL AND localized_content_hash IS NULL AND kannada_review_id IS NULL AND kannada_review_kind IS NULL AND kannada_review_decision IS NULL AND kannada_appointment_id IS NULL AND kannada_appointment_event_id IS NULL) OR (localized_variant_id IS NOT NULL AND localized_content_hash IS NOT NULL AND kannada_review_id IS NOT NULL AND kannada_review_kind = 'kannada' AND kannada_review_decision = 'approved' AND kannada_appointment_id IS NOT NULL AND kannada_appointment_event_id IS NOT NULL))",
            );
            DB::statement(
                "ALTER TABLE publication_media_components ADD CONSTRAINT publication_media_components_rights_check CHECK (rights_evidence_status = 'approved')",
            );
            DB::statement(
                'ALTER TABLE publication_heads ADD CONSTRAINT publication_heads_suspension_check CHECK (is_suspended = FALSE OR last_event_id IS NOT NULL)',
            );
            DB::statement(
                'ALTER TABLE publication_heads ADD CONSTRAINT publication_heads_active_pair_check CHECK ((active_curriculum_version_id IS NULL AND active_publication_id IS NULL) OR (active_curriculum_version_id IS NOT NULL AND active_publication_id IS NOT NULL))',
            );
            DB::statement(
                "ALTER TABLE publication_commands ADD CONSTRAINT publication_commands_actor_check CHECK ((actor_type = 'owner_membership' AND actor_membership_id IS NOT NULL) OR (actor_type = 'platform_user' AND actor_membership_id IS NULL))",
            );
            DB::statement(
                'ALTER TABLE curriculum_share_grants ADD CONSTRAINT curriculum_share_grants_distinct_tenants_check CHECK (organization_id <> recipient_organization_id)',
            );
            DB::statement(
                'ALTER TABLE curriculum_share_grants ADD CONSTRAINT curriculum_share_grants_use_check CHECK (allow_preview OR allow_enrollment OR allow_clone)',
            );
            DB::statement(
                'ALTER TABLE curriculum_share_grants ADD CONSTRAINT curriculum_share_grants_validity_check CHECK (expires_at IS NULL OR expires_at > effective_at)',
            );
            DB::statement(
                "ALTER TABLE curriculum_share_grant_events ADD CONSTRAINT curriculum_share_grant_events_type_check CHECK (event_type IN ('issued', 'withdrawn'))",
            );
            DB::statement(
                "ALTER TABLE curriculum_share_grant_heads ADD CONSTRAINT curriculum_share_grant_heads_status_check CHECK (status IN ('active', 'withdrawn'))",
            );

            foreach ([
                'programs',
                'curriculum_publications',
                'publication_graph_components',
                'publication_content_components',
                'publication_media_components',
                'publication_events',
                'publication_commands',
                'curriculum_share_grants',
                'curriculum_share_grant_events',
                'curriculum_clones',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_append_only BEFORE UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION curriculum_reject_append_only_mutation()',
                    $table,
                    $table,
                ));
            }

            foreach ([
                'publication_heads',
                'curriculum_share_grant_heads',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_organization_immutable BEFORE UPDATE ON %s FOR EACH ROW EXECUTE FUNCTION curriculum_reject_organization_change()',
                    $table,
                    $table,
                ));
            }

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_validate_emergency_evidence()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    expected_action text;
BEGIN
    IF NEW.event_type NOT IN ('suspended', 'resumed') THEN
        RETURN NEW;
    END IF;

    IF NEW.actor_type = 'owner_membership' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM memberships membership
            JOIN authentication_sessions session
              ON session.user_id = membership.user_id
            WHERE membership.organization_id = NEW.organization_id
              AND membership.id = NEW.actor_membership_id
              AND session.id = NEW.authentication_session_id
              AND session.is_privileged = TRUE
              AND session.revoked_at IS NULL
              AND session.mfa_verified_at IS NOT NULL
              AND session.mfa_verified_at <= NEW.effective_at
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
        ) THEN
            RAISE EXCEPTION 'Owner emergency evidence is not bound to the accountable actor session'
                USING ERRCODE = '23514';
        END IF;
    ELSIF NEW.actor_type = 'platform_user' THEN
        expected_action := 'curriculum.publication.' || NEW.event_type;

        IF NOT EXISTS (
            SELECT 1
            FROM authentication_sessions session
            WHERE session.id = NEW.authentication_session_id
              AND session.user_id = NEW.platform_actor_user_id
              AND session.is_privileged = TRUE
              AND session.revoked_at IS NULL
              AND session.mfa_verified_at IS NOT NULL
              AND session.mfa_verified_at <= NEW.effective_at
              AND session.idle_expires_at > NEW.effective_at
              AND session.absolute_expires_at > NEW.effective_at
        ) OR NOT EXISTS (
            SELECT 1
            FROM audit_events audit
            WHERE audit.id = NEW.platform_audit_event_id
              AND audit.scope_kind = 'platform'
              AND audit.organization_id IS NULL
              AND audit.target_organization_id = NEW.organization_id
              AND audit.actor_user_id = NEW.platform_actor_user_id
              AND audit.actor_membership_id IS NULL
              AND audit.capability_key = 'content.emergency-suspend'
              AND audit.reason_key = NEW.reason_key
              AND audit.action = expected_action
              AND audit.subject_type = 'program'
              AND audit.subject_id = NEW.program_id
              AND audit.correlation_id = NEW.correlation_id
              AND audit.occurred_at <= NEW.effective_at
        ) THEN
            RAISE EXCEPTION 'Platform emergency evidence is not bound to the exact authorization audit'
                USING ERRCODE = '23514';
        END IF;
    ELSE
        RAISE EXCEPTION 'Emergency evidence has an unsupported actor type'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            DB::statement(
                'CREATE TRIGGER publication_events_emergency_evidence BEFORE INSERT ON publication_events FOR EACH ROW EXECUTE FUNCTION curriculum_validate_emergency_evidence()',
            );

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_reject_published_graph_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    owner_id text;
    version_id text;
BEGIN
    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        owner_id := OLD.organization_id;
        IF TG_TABLE_NAME = 'curriculum_versions' THEN
            version_id := OLD.id;
        ELSE
            version_id := OLD.curriculum_version_id;
        END IF;

        IF EXISTS (
            SELECT 1
            FROM curriculum_publications
            WHERE organization_id = owner_id
              AND curriculum_version_id = version_id
        ) THEN
            RAISE EXCEPTION 'Published curriculum graph is immutable'
                USING ERRCODE = '55000';
        END IF;
    END IF;

    IF TG_OP IN ('UPDATE', 'INSERT') THEN
        owner_id := NEW.organization_id;
        IF TG_TABLE_NAME = 'curriculum_versions' THEN
            version_id := NEW.id;
        ELSE
            version_id := NEW.curriculum_version_id;
        END IF;

        IF EXISTS (
            SELECT 1
            FROM curriculum_publications
            WHERE organization_id = owner_id
              AND curriculum_version_id = version_id
        ) THEN
            RAISE EXCEPTION 'Published curriculum graph is immutable'
                USING ERRCODE = '55000';
        END IF;
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            foreach ([
                'curriculum_versions',
                'learning_objectives',
                'curriculum_modules',
                'objective_prerequisites',
                'lessons',
                'lesson_objectives',
                'content_documents',
                'content_revisions',
                'content_revision_heads',
                'localized_variants',
                'learning_items',
                'curriculum_missions',
                'mission_items',
                'content_revision_media_assets',
                'curriculum_graph_revisions',
                'curriculum_graph_heads',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_published_graph_immutable BEFORE INSERT OR UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION curriculum_reject_published_graph_mutation()',
                    $table,
                    $table,
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'DROP TRIGGER IF EXISTS programs_append_only ON programs',
            );

            foreach ([
                'curriculum_versions',
                'learning_objectives',
                'curriculum_modules',
                'objective_prerequisites',
                'lessons',
                'lesson_objectives',
                'content_documents',
                'content_revisions',
                'content_revision_heads',
                'localized_variants',
                'learning_items',
                'curriculum_missions',
                'mission_items',
                'content_revision_media_assets',
                'curriculum_graph_revisions',
                'curriculum_graph_heads',
            ] as $table) {
                DB::statement(sprintf(
                    'DROP TRIGGER IF EXISTS %s_published_graph_immutable ON %s',
                    $table,
                    $table,
                ));
            }
        }

        Schema::dropIfExists('curriculum_clones');
        Schema::dropIfExists('curriculum_share_grant_heads');
        Schema::dropIfExists('curriculum_share_grant_events');
        Schema::dropIfExists('curriculum_share_grants');
        Schema::dropIfExists('publication_commands');
        Schema::dropIfExists('publication_heads');
        Schema::dropIfExists('publication_events');
        Schema::dropIfExists('publication_media_components');
        Schema::dropIfExists('publication_content_components');
        Schema::dropIfExists('publication_graph_components');
        Schema::dropIfExists('curriculum_publications');

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropUnique(
                'audit_events_authorization_nonce_unique',
            );
            $table->dropColumn('authorization_nonce');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'DROP FUNCTION IF EXISTS curriculum_reject_published_graph_mutation()',
            );
            DB::statement(
                'DROP FUNCTION IF EXISTS curriculum_validate_emergency_evidence()',
            );
        }
    }
};
