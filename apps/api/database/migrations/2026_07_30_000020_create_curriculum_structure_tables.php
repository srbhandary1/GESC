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
        Schema::create('programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('key', 100);
            $table->string('internal_name', 160);
            $table->ulid('created_by_membership_id')->nullable();
            $table->string('created_by_attribution', 120);
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'key']);
            $table->foreign(
                ['organization_id', 'created_by_membership_id'],
                'programs_creator_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->string('key', 100);
            $table->ulid('created_by_membership_id')->nullable();
            $table->string('created_by_attribution', 120);
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'program_id', 'id'],
                'curriculum_versions_program_id_unique',
            );
            $table->unique(['organization_id', 'program_id', 'key']);
            $table->foreign(
                ['organization_id', 'program_id'],
                'curriculum_versions_program_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('programs')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'created_by_membership_id'],
                'curriculum_versions_creator_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('learning_objectives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->string('key', 100);
            $table->string('outcome_code', 120);
            $table->unsignedInteger('sequence');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'learning_objectives_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'key'],
                'learning_objectives_version_key_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'learning_objectives_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_modules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->string('key', 100);
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'curriculum_modules_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'key'],
                'curriculum_modules_version_key_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'sequence'],
                'curriculum_modules_version_sequence_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'curriculum_modules_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
        });

        Schema::create('objective_prerequisites', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('objective_id');
            $table->ulid('prerequisite_objective_id');
            $table->timestampsTz();

            $table->primary(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'objective_id',
                    'prerequisite_objective_id',
                ],
                'objective_prerequisites_primary',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'objective_id',
                ],
                'objective_prerequisites_objective_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('learning_objectives')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'prerequisite_objective_id',
                ],
                'objective_prerequisites_required_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('learning_objectives')
                ->restrictOnDelete();
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_module_id');
            $table->string('key', 100);
            $table->unsignedInteger('sequence');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'lessons_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'key'],
                'lessons_version_key_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_module_id',
                    'sequence',
                ],
                'lessons_module_sequence_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'lessons_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_module_id',
                ],
                'lessons_module_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_modules')
                ->restrictOnDelete();
        });

        Schema::create('lesson_objectives', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('lesson_id');
            $table->ulid('objective_id');
            $table->timestampsTz();

            $table->primary(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'lesson_id',
                    'objective_id',
                ],
                'lesson_objectives_primary',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'lesson_id'],
                'lesson_objectives_lesson_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('lessons')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'objective_id'],
                'lesson_objectives_objective_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('learning_objectives')
                ->restrictOnDelete();
        });

        Schema::create('content_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->string('key', 120);
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'content_documents_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'key'],
                'content_documents_version_key_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'content_documents_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('content_document_id');
            $table->ulid('parent_revision_id')->nullable();
            $table->unsignedInteger('revision_number');
            $table->string('source_locale', 20)->default('en-IN');
            $table->string('content_type', 48);
            $table->boolean('learner_visible');
            $table->string('localization_requirement', 40);
            $table->boolean('pedagogically_authoritative')->default(true);
            $table->json('payload');
            $table->char('content_hash', 64);
            $table->ulid('authored_by_membership_id')->nullable();
            $table->string('author_attribution', 120);
            $table->timestampTz('authored_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'content_revisions_version_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                    'id',
                ],
                'content_revisions_document_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'id',
                    'content_hash',
                ],
                'content_revisions_id_hash_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                    'revision_number',
                ],
                'content_revisions_document_number_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                ],
                'content_revisions_document_tenant_fk',
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
                    'parent_revision_id',
                ],
                'content_revisions_parent_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'content_document_id',
                'id',
            ])->on('content_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'authored_by_membership_id'],
                'content_revisions_author_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('content_revision_heads', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('content_document_id');
            $table->ulid('content_revision_id');
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'content_document_id'],
                'content_revision_heads_primary',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_revision_id',
                ],
                'content_revision_heads_revision_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                ],
                'content_revision_heads_document_tenant_fk',
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
                    'content_revision_id',
                ],
                'content_revision_heads_revision_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'content_document_id',
                'id',
            ])->on('content_revisions')
                ->restrictOnDelete();
        });

        Schema::create('localized_variants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('source_revision_id');
            $table->ulid('parent_variant_id')->nullable();
            $table->string('locale', 20);
            $table->unsignedInteger('revision_number');
            $table->char('source_content_hash', 64);
            $table->json('payload');
            $table->char('content_hash', 64);
            $table->ulid('translated_by_membership_id');
            $table->string('translator_attribution', 120);
            $table->timestampTz('translated_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'localized_variants_version_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'source_revision_id',
                    'locale',
                    'id',
                ],
                'localized_variants_source_locale_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'id',
                    'source_revision_id',
                    'source_content_hash',
                    'content_hash',
                ],
                'localized_variants_id_hashes_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'source_revision_id',
                    'locale',
                    'revision_number',
                ],
                'localized_variants_source_locale_number_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'source_revision_id',
                    'source_content_hash',
                ],
                'localized_variants_source_tenant_fk',
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
                    'source_revision_id',
                    'locale',
                    'parent_variant_id',
                ],
                'localized_variants_parent_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'source_revision_id',
                'locale',
                'id',
            ])->on('localized_variants')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'translated_by_membership_id'],
                'localized_variants_translator_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('learning_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('lesson_id');
            $table->ulid('content_document_id');
            $table->string('key', 100);
            $table->string('item_type', 48);
            $table->unsignedInteger('sequence');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'learning_items_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'key'],
                'learning_items_version_key_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'lesson_id'],
                'learning_items_lesson_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('lessons')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'content_document_id',
                ],
                'learning_items_document_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('content_documents')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_missions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('instruction_content_document_id');
            $table->string('key', 100);
            $table->unsignedInteger('sequence');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'curriculum_missions_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'key'],
                'curriculum_missions_version_key_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'curriculum_missions_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'instruction_content_document_id',
                ],
                'curriculum_missions_document_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('content_documents')
                ->restrictOnDelete();
        });

        Schema::create('mission_items', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('mission_id');
            $table->ulid('learning_item_id');
            $table->unsignedInteger('sequence');
            $table->timestampsTz();

            $table->primary(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'mission_id',
                    'learning_item_id',
                ],
                'mission_items_primary',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'mission_id',
                    'sequence',
                ],
                'mission_items_sequence_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'mission_id'],
                'mission_items_mission_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_missions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'learning_item_id',
                ],
                'mission_items_item_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('learning_items')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_graph_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('parent_revision_id')->nullable();
            $table->unsignedInteger('parent_revision_number')->nullable();
            $table->unsignedInteger('revision_number');
            $table->json('graph');
            $table->char('graph_hash', 64);
            $table->ulid('authored_by_membership_id')->nullable();
            $table->string('author_attribution', 120);
            $table->timestampTz('authored_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'curriculum_graph_revisions_version_id_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'id',
                    'revision_number',
                ],
                'curriculum_graph_revisions_id_number_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'id',
                    'graph_hash',
                ],
                'curriculum_graph_revisions_id_hash_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'graph_hash',
                ],
                'curriculum_graph_revisions_version_hash_unique',
            );
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'revision_number',
                ],
                'curriculum_graph_revisions_version_number_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'curriculum_graph_revisions_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'parent_revision_id',
                    'parent_revision_number',
                ],
                'curriculum_graph_revisions_parent_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'revision_number',
            ])->on('curriculum_graph_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'authored_by_membership_id'],
                'curriculum_graph_revisions_author_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_graph_heads', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->ulid('curriculum_graph_revision_id');
            $table->char('graph_hash', 64);
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'curriculum_version_id'],
                'curriculum_graph_heads_primary',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'curriculum_graph_heads_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_graph_revision_id',
                    'graph_hash',
                ],
                'curriculum_graph_heads_revision_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
                'graph_hash',
            ])->on('curriculum_graph_revisions')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_type_check CHECK (content_type IN ('title', 'instruction', 'explanation', 'dialogue', 'feedback', 'learner_action', 'assessment_rationale', 'safety_note', 'transcript', 'model_text'))",
            );
            DB::statement(
                "ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_localization_check CHECK (localization_requirement IN ('kn_required', 'english_only_pedagogical'))",
            );
            DB::statement(
                "ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_english_only_scope_check CHECK (localization_requirement <> 'english_only_pedagogical' OR content_type NOT IN ('instruction', 'explanation', 'feedback', 'learner_action'))",
            );
            DB::statement(
                "ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_source_locale_check CHECK (source_locale = 'en-IN')",
            );
            DB::statement(
                "ALTER TABLE localized_variants ADD CONSTRAINT localized_variants_locale_check CHECK (locale = 'kn-IN')",
            );
            DB::statement(
                'ALTER TABLE objective_prerequisites ADD CONSTRAINT objective_prerequisites_distinct_check CHECK (objective_id <> prerequisite_objective_id)',
            );
            DB::statement(
                'ALTER TABLE curriculum_graph_revisions ADD CONSTRAINT curriculum_graph_revisions_number_check CHECK (revision_number > 0)',
            );
            DB::statement(
                'ALTER TABLE curriculum_graph_revisions ADD CONSTRAINT curriculum_graph_revisions_parent_check CHECK ((revision_number = 1 AND parent_revision_id IS NULL AND parent_revision_number IS NULL) OR (revision_number > 1 AND parent_revision_id IS NOT NULL AND parent_revision_number = revision_number - 1 AND parent_revision_id <> id))',
            );
            DB::statement(
                'ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_number_check CHECK (revision_number > 0)',
            );
            DB::statement(
                'ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_parent_check CHECK ((revision_number = 1 AND parent_revision_id IS NULL) OR (revision_number > 1 AND parent_revision_id IS NOT NULL AND parent_revision_id <> id))',
            );
            DB::statement(
                'ALTER TABLE localized_variants ADD CONSTRAINT localized_variants_number_check CHECK (revision_number > 0)',
            );
            DB::statement(
                'ALTER TABLE localized_variants ADD CONSTRAINT localized_variants_parent_check CHECK ((revision_number = 1 AND parent_variant_id IS NULL) OR (revision_number > 1 AND parent_variant_id IS NOT NULL AND parent_variant_id <> id))',
            );

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_reject_append_only_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION '% is append-only', TG_TABLE_NAME USING ERRCODE = '55000';
END;
$$;
SQL);

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_validate_content_lineage()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.revision_number = 1 THEN
        RETURN NEW;
    END IF;

    IF TG_TABLE_NAME = 'content_revisions' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM content_revisions parent
            WHERE parent.organization_id = NEW.organization_id
              AND parent.curriculum_version_id = NEW.curriculum_version_id
              AND parent.content_document_id = NEW.content_document_id
              AND parent.id = NEW.parent_revision_id
              AND parent.revision_number = NEW.revision_number - 1
        ) THEN
            RAISE EXCEPTION 'Content revision parent must be the immediately preceding revision'
                USING ERRCODE = '23514';
        END IF;
    ELSIF TG_TABLE_NAME = 'localized_variants' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM localized_variants parent
            WHERE parent.organization_id = NEW.organization_id
              AND parent.curriculum_version_id = NEW.curriculum_version_id
              AND parent.source_revision_id = NEW.source_revision_id
              AND parent.locale = NEW.locale
              AND parent.id = NEW.parent_variant_id
              AND parent.revision_number = NEW.revision_number - 1
        ) THEN
            RAISE EXCEPTION 'Localized variant parent must be the immediately preceding revision'
                USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION curriculum_reject_organization_change()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.organization_id IS DISTINCT FROM NEW.organization_id THEN
        RAISE EXCEPTION '% organization ownership is immutable', TG_TABLE_NAME
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            foreach ([
                'content_revisions',
                'localized_variants',
                'curriculum_graph_revisions',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_append_only BEFORE UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION curriculum_reject_append_only_mutation()',
                    $table,
                    $table,
                ));
            }

            foreach ([
                'content_revisions',
                'localized_variants',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_lineage BEFORE INSERT ON %s FOR EACH ROW EXECUTE FUNCTION curriculum_validate_content_lineage()',
                    $table,
                    $table,
                ));
            }

            foreach ([
                'programs',
                'curriculum_versions',
                'learning_objectives',
                'curriculum_modules',
                'objective_prerequisites',
                'lessons',
                'lesson_objectives',
                'content_documents',
                'content_revision_heads',
                'learning_items',
                'curriculum_missions',
                'mission_items',
                'curriculum_graph_heads',
            ] as $table) {
                DB::statement(sprintf(
                    'CREATE TRIGGER %s_organization_immutable BEFORE UPDATE ON %s FOR EACH ROW EXECUTE FUNCTION curriculum_reject_organization_change()',
                    $table,
                    $table,
                ));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_graph_heads');
        Schema::dropIfExists('curriculum_graph_revisions');
        Schema::dropIfExists('mission_items');
        Schema::dropIfExists('curriculum_missions');
        Schema::dropIfExists('learning_items');
        Schema::dropIfExists('localized_variants');
        Schema::dropIfExists('content_revision_heads');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_documents');
        Schema::dropIfExists('lesson_objectives');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('objective_prerequisites');
        Schema::dropIfExists('curriculum_modules');
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('curriculum_versions');
        Schema::dropIfExists('programs');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'DROP FUNCTION IF EXISTS curriculum_reject_append_only_mutation()',
            );
            DB::statement(
                'DROP FUNCTION IF EXISTS curriculum_validate_content_lineage()',
            );
            DB::statement(
                'DROP FUNCTION IF EXISTS curriculum_reject_organization_change()',
            );
        }
    }
};
