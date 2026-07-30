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
        Schema::create('authority_appointments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignUlid('appointee_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->ulid('appointee_membership_id');
            $table->string('authority_kind', 48);
            $table->string('scope_type', 40)->default('organization');
            $table->ulid('program_id')->nullable();
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until')->nullable();
            $table->text('qualification_basis');
            $table->string('approval_evidence_key', 120);
            $table->ulid('appointed_by_membership_id');
            $table->timestampTz('appointed_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'id', 'appointee_membership_id'],
                'authority_appointments_reviewer_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'id',
                    'authority_kind',
                    'appointee_membership_id',
                ],
                'authority_appointments_kind_reviewer_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'appointee_user_id',
                    'appointee_membership_id',
                ],
                'authority_appointments_appointee_tenant_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'program_id'],
                'authority_appointments_program_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('programs')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'appointed_by_membership_id'],
                'authority_appointments_appointing_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'authority_kind', 'valid_from'],
                'authority_appointments_lookup_index',
            );
        });

        Schema::create('authority_appointment_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('authority_appointment_id');
            $table->string('event_type', 40);
            $table->ulid('actor_membership_id');
            $table->string('reason_key', 100)->nullable();
            $table->boolean('invalidates_prior_approvals')->default(false);
            $table->timestampTz('effective_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'authority_appointment_id', 'id'],
                'authority_appointment_events_appointment_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'authority_appointment_id',
                    'id',
                    'event_type',
                ],
                'authority_appointment_events_type_unique',
            );
            $table->foreign(
                ['organization_id', 'authority_appointment_id'],
                'authority_appointment_events_appointment_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('authority_appointments')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'authority_appointment_events_actor_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                [
                    'organization_id',
                    'authority_appointment_id',
                    'effective_at',
                ],
                'authority_appointment_events_timeline_index',
            );
        });

        Schema::create('content_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->string('review_kind', 32);
            $table->ulid('source_revision_id');
            $table->ulid('localized_variant_id')->nullable();
            $table->ulid('authority_appointment_id');
            $table->ulid('authority_appointment_event_id');
            $table->ulid('reviewer_membership_id');
            $table->string('decision', 24);
            $table->char('source_content_hash', 64);
            $table->char('localized_content_hash', 64)->nullable();
            $table->string('rationale_key', 100)->nullable();
            $table->timestampTz('reviewed_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'content_reviews_version_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'id',
                    'review_kind',
                    'decision',
                    'source_revision_id',
                    'source_content_hash',
                    'authority_appointment_id',
                    'authority_appointment_event_id',
                ],
                'content_reviews_academic_evidence_unique',
            );
            $table->unique(
                [
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
                ],
                'content_reviews_kannada_evidence_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'source_revision_id',
                    'source_content_hash',
                ],
                'content_reviews_source_tenant_fk',
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
                    'localized_variant_id',
                    'source_revision_id',
                    'source_content_hash',
                    'localized_content_hash',
                ],
                'content_reviews_variant_tenant_fk',
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
                    'authority_appointment_id',
                    'reviewer_membership_id',
                ],
                'content_reviews_reviewer_appointment_tenant_fk',
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
                'content_reviews_appointment_event_tenant_fk',
            )->references([
                'organization_id',
                'authority_appointment_id',
                'id',
            ])->on('authority_appointment_events')
                ->restrictOnDelete();
            $table->index(
                [
                    'organization_id',
                    'source_revision_id',
                    'review_kind',
                    'reviewed_at',
                ],
                'content_reviews_source_timeline_index',
            );
        });

        Schema::create('curriculum_graph_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_graph_revision_id');
            $table->char('graph_hash', 64);
            $table->string('authority_kind', 48)
                ->default('english_academic_reviewer');
            $table->ulid('authority_appointment_id');
            $table->ulid('authority_appointment_event_id');
            $table->string('authority_appointment_event_type', 40)
                ->default('accepted');
            $table->ulid('reviewer_membership_id');
            $table->string('decision', 24);
            $table->string('rationale_key', 100)->nullable();
            $table->timestampTz('reviewed_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'curriculum_graph_reviews_version_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'id',
                    'curriculum_graph_revision_id',
                    'graph_hash',
                    'decision',
                    'authority_appointment_id',
                    'authority_appointment_event_id',
                    'reviewer_membership_id',
                ],
                'curriculum_graph_reviews_evidence_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_graph_revision_id',
                    'graph_hash',
                ],
                'curriculum_graph_reviews_revision_tenant_fk',
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
                    'authority_appointment_id',
                    'authority_kind',
                    'reviewer_membership_id',
                ],
                'curriculum_graph_reviews_reviewer_tenant_fk',
            )->references([
                'organization_id',
                'id',
                'authority_kind',
                'appointee_membership_id',
            ])->on('authority_appointments')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'authority_appointment_id',
                    'authority_appointment_event_id',
                    'authority_appointment_event_type',
                ],
                'curriculum_graph_reviews_appointment_event_fk',
            )->references([
                'organization_id',
                'authority_appointment_id',
                'id',
                'event_type',
            ])->on('authority_appointment_events')
                ->restrictOnDelete();
            $table->index(
                [
                    'organization_id',
                    'curriculum_graph_revision_id',
                    'reviewed_at',
                ],
                'curriculum_graph_reviews_timeline_index',
            );
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('key', 120);
            $table->string('asset_kind', 48);
            $table->string('storage_object_key', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->char('asset_hash', 64);
            $table->ulid('uploaded_by_membership_id')->nullable();
            $table->string('uploader_attribution', 120);
            $table->timestampTz('uploaded_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'key']);
            $table->unique(
                ['organization_id', 'id', 'asset_hash'],
                'media_assets_id_hash_unique',
            );
            $table->foreign(
                ['organization_id', 'uploaded_by_membership_id'],
                'media_assets_uploader_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('media_rights_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('media_asset_id');
            $table->ulid('parent_revision_id')->nullable();
            $table->unsignedInteger('revision_number');
            $table->string('evidence_status', 24);
            $table->char('evidence_hash', 64);
            $table->char('selected_asset_hash', 64);
            $table->string('rights_holder_reference', 120);
            $table->string('provenance_reference', 120);
            $table->string('rights_basis_key', 100);
            $table->string('license_or_contract_reference', 240);
            $table->json('permitted_release_channels');
            $table->json('permitted_territories');
            $table->boolean('learner_delivery_allowed')->default(false);
            $table->boolean('sharing_allowed')->default(false);
            $table->boolean('derivative_use_allowed')->default(false);
            $table->timestampTz('effective_at');
            $table->timestampTz('expires_at')->nullable();
            $table->string('retention_policy_key', 100);
            $table->string('deletion_constraint_key', 100);
            $table->ulid('confirmed_by_membership_id')->nullable();
            $table->ulid('rights_appointment_id')->nullable();
            $table->ulid('rights_appointment_event_id')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'program_id', 'media_asset_id', 'id'],
                'media_rights_revisions_asset_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'program_id',
                    'media_asset_id',
                    'revision_number',
                ],
                'media_rights_revisions_asset_number_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'program_id',
                    'media_asset_id',
                    'id',
                    'evidence_status',
                    'evidence_hash',
                    'selected_asset_hash',
                ],
                'media_rights_revisions_evidence_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id'],
                'media_rights_revisions_program_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('programs')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'media_asset_id',
                    'selected_asset_hash',
                ],
                'media_rights_revisions_asset_tenant_fk',
            )->references(['organization_id', 'id', 'asset_hash'])
                ->on('media_assets')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'media_asset_id',
                    'parent_revision_id',
                ],
                'media_rights_revisions_parent_tenant_fk',
            )->references([
                'organization_id',
                'program_id',
                'media_asset_id',
                'id',
            ])->on('media_rights_revisions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'rights_appointment_id',
                    'confirmed_by_membership_id',
                ],
                'media_rights_revisions_confirmer_tenant_fk',
            )->references([
                'organization_id',
                'id',
                'appointee_membership_id',
            ])->on('authority_appointments')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'rights_appointment_id',
                    'rights_appointment_event_id',
                ],
                'media_rights_revisions_appointment_event_tenant_fk',
            )->references([
                'organization_id',
                'authority_appointment_id',
                'id',
            ])->on('authority_appointment_events')
                ->restrictOnDelete();
        });

        Schema::create('content_revision_media_assets', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('content_revision_id');
            $table->ulid('media_asset_id');
            $table->string('usage_key', 80);
            $table->unsignedInteger('sequence');
            $table->timestampsTz();

            $table->primary(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_revision_id',
                    'media_asset_id',
                    'usage_key',
                ],
                'content_revision_media_assets_primary',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_revision_id',
                ],
                'content_revision_media_assets_revision_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('content_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'media_asset_id'],
                'content_revision_media_assets_asset_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('media_assets')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE authority_appointments ADD CONSTRAINT authority_appointments_kind_check CHECK (authority_kind IN ('english_academic_reviewer', 'kannada_reviewer', 'rights_confirmer'))",
            );
            DB::statement(
                "ALTER TABLE authority_appointments ADD CONSTRAINT authority_appointments_scope_check CHECK ((scope_type = 'organization' AND program_id IS NULL) OR (scope_type = 'program' AND program_id IS NOT NULL))",
            );
            DB::statement(
                'ALTER TABLE authority_appointments ADD CONSTRAINT authority_appointments_validity_check CHECK (valid_until IS NULL OR valid_until > valid_from)',
            );
            DB::statement(
                "ALTER TABLE authority_appointment_events ADD CONSTRAINT authority_appointment_events_type_check CHECK (event_type IN ('appointed', 'accepted', 'surrendered', 'revoked', 'expired', 'revoked_for_cause'))",
            );
            DB::statement(
                "ALTER TABLE authority_appointment_events ADD CONSTRAINT authority_appointment_events_invalidation_check CHECK (invalidates_prior_approvals = FALSE OR event_type = 'revoked_for_cause')",
            );
            DB::statement(
                "ALTER TABLE content_reviews ADD CONSTRAINT content_reviews_kind_check CHECK (review_kind IN ('academic_en', 'kannada'))",
            );
            DB::statement(
                "ALTER TABLE content_reviews ADD CONSTRAINT content_reviews_decision_check CHECK (decision IN ('approved', 'rejected', 'withdrawn'))",
            );
            DB::statement(
                "ALTER TABLE content_reviews ADD CONSTRAINT content_reviews_target_check CHECK ((review_kind = 'academic_en' AND localized_variant_id IS NULL AND localized_content_hash IS NULL) OR (review_kind = 'kannada' AND localized_variant_id IS NOT NULL AND localized_content_hash IS NOT NULL))",
            );
            DB::statement(
                "ALTER TABLE curriculum_graph_reviews ADD CONSTRAINT curriculum_graph_reviews_authority_check CHECK (authority_kind = 'english_academic_reviewer' AND authority_appointment_event_type = 'accepted')",
            );
            DB::statement(
                "ALTER TABLE curriculum_graph_reviews ADD CONSTRAINT curriculum_graph_reviews_decision_check CHECK (decision IN ('approved', 'rejected', 'withdrawn'))",
            );
            DB::statement(
                "ALTER TABLE media_rights_revisions ADD CONSTRAINT media_rights_revisions_status_check CHECK (evidence_status IN ('pending', 'approved', 'rejected', 'withdrawn'))",
            );
            DB::statement(
                'ALTER TABLE media_rights_revisions ADD CONSTRAINT media_rights_revisions_number_check CHECK (revision_number > 0)',
            );
            DB::statement(
                'ALTER TABLE media_rights_revisions ADD CONSTRAINT media_rights_revisions_parent_check CHECK ((revision_number = 1 AND parent_revision_id IS NULL) OR (revision_number > 1 AND parent_revision_id IS NOT NULL AND parent_revision_id <> id))',
            );
            DB::statement(
                'ALTER TABLE media_rights_revisions ADD CONSTRAINT media_rights_revisions_validity_check CHECK (expires_at IS NULL OR expires_at > effective_at)',
            );
            DB::statement(
                "ALTER TABLE media_rights_revisions ADD CONSTRAINT media_rights_revisions_confirmation_check CHECK ((evidence_status IN ('approved', 'rejected', 'withdrawn') AND confirmed_by_membership_id IS NOT NULL AND rights_appointment_id IS NOT NULL AND rights_appointment_event_id IS NOT NULL AND confirmed_at IS NOT NULL) OR (evidence_status = 'pending' AND confirmed_by_membership_id IS NULL AND rights_appointment_id IS NULL AND rights_appointment_event_id IS NULL AND confirmed_at IS NULL))",
            );

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_validate_rights_lineage()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.revision_number = 1 THEN
        RETURN NEW;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM media_rights_revisions parent
        WHERE parent.organization_id = NEW.organization_id
          AND parent.program_id = NEW.program_id
          AND parent.media_asset_id = NEW.media_asset_id
          AND parent.id = NEW.parent_revision_id
          AND parent.revision_number = NEW.revision_number - 1
    ) THEN
        RAISE EXCEPTION 'Media rights parent must be the immediately preceding revision'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_validate_governance_authority()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    expected_kind text;
BEGIN
    IF TG_TABLE_NAME = 'content_reviews' THEN
        expected_kind := CASE NEW.review_kind
            WHEN 'academic_en' THEN 'english_academic_reviewer'
            WHEN 'kannada' THEN 'kannada_reviewer'
            ELSE NULL
        END;

        IF expected_kind IS NULL OR NOT EXISTS (
            SELECT 1
            FROM authority_appointments appointment
            JOIN authority_appointment_events acceptance
              ON acceptance.organization_id = appointment.organization_id
             AND acceptance.authority_appointment_id = appointment.id
            WHERE appointment.organization_id = NEW.organization_id
              AND appointment.id = NEW.authority_appointment_id
              AND appointment.authority_kind = expected_kind
              AND appointment.appointee_membership_id = NEW.reviewer_membership_id
              AND acceptance.id = NEW.authority_appointment_event_id
              AND acceptance.event_type = 'accepted'
        ) THEN
            RAISE EXCEPTION 'Content review authority evidence is not an accepted appointment of the required kind'
                USING ERRCODE = '23514';
        END IF;
    ELSIF TG_TABLE_NAME = 'media_rights_revisions'
        AND NEW.evidence_status <> 'pending'
        AND NOT EXISTS (
            SELECT 1
            FROM authority_appointments appointment
            JOIN authority_appointment_events acceptance
              ON acceptance.organization_id = appointment.organization_id
             AND acceptance.authority_appointment_id = appointment.id
            WHERE appointment.organization_id = NEW.organization_id
              AND appointment.id = NEW.rights_appointment_id
              AND appointment.authority_kind = 'rights_confirmer'
              AND appointment.appointee_membership_id = NEW.confirmed_by_membership_id
              AND acceptance.id = NEW.rights_appointment_event_id
              AND acceptance.event_type = 'accepted'
        ) THEN
        RAISE EXCEPTION 'Media rights authority evidence is not an accepted rights-confirmer appointment'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            foreach ([
                'authority_appointments',
                'authority_appointment_events',
                'content_reviews',
                'curriculum_graph_reviews',
                'media_assets',
                'media_rights_revisions',
                'content_revision_media_assets',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_append_only BEFORE UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION curriculum_reject_append_only_mutation()',
                    $table,
                    $table,
                ));
            }

            DB::statement(
                'CREATE TRIGGER media_rights_revisions_lineage BEFORE INSERT ON media_rights_revisions FOR EACH ROW EXECUTE FUNCTION curriculum_validate_rights_lineage()',
            );
            DB::statement(
                'CREATE TRIGGER content_reviews_authority BEFORE INSERT ON content_reviews FOR EACH ROW EXECUTE FUNCTION curriculum_validate_governance_authority()',
            );
            DB::statement(
                'CREATE TRIGGER media_rights_revisions_authority BEFORE INSERT ON media_rights_revisions FOR EACH ROW EXECUTE FUNCTION curriculum_validate_governance_authority()',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revision_media_assets');
        Schema::dropIfExists('media_rights_revisions');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('curriculum_graph_reviews');
        Schema::dropIfExists('content_reviews');
        Schema::dropIfExists('authority_appointment_events');
        Schema::dropIfExists('authority_appointments');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'DROP FUNCTION IF EXISTS curriculum_validate_rights_lineage()',
            );
            DB::statement(
                'DROP FUNCTION IF EXISTS curriculum_validate_governance_authority()',
            );
        }
    }
};
