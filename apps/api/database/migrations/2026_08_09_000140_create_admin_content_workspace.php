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
        Schema::table('programs', function (Blueprint $table): void {
            $table->unsignedInteger('descriptor_version')
                ->default(1)
                ->after('internal_name');
        });

        Schema::create('curriculum_levels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->string('key', 100);
            $table->unsignedInteger('sequence');
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'curriculum_levels_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'key'],
                'curriculum_levels_version_key_unique',
            );
            $table->foreign(
                ['organization_id', 'curriculum_version_id'],
                'curriculum_levels_version_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
        });

        Schema::table('learning_objectives', function (Blueprint $table): void {
            $table->timestampTz('retired_at')->nullable()->after('sequence');
        });
        Schema::table('curriculum_modules', function (Blueprint $table): void {
            $table->ulid('curriculum_level_id')
                ->nullable()
                ->after('curriculum_version_id');
            $table->timestampTz('retired_at')
                ->nullable()
                ->after('estimated_minutes');
            $table->dropUnique('curriculum_modules_version_sequence_unique');
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_level_id',
                ],
                'curriculum_modules_level_tenant_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_levels')
                ->restrictOnDelete();
        });
        Schema::table('lessons', function (Blueprint $table): void {
            $table->timestampTz('retired_at')->nullable()->after('sequence');
        });
        Schema::table('learning_items', function (Blueprint $table): void {
            $table->timestampTz('retired_at')->nullable()->after('sequence');
        });
        Schema::table('curriculum_missions', function (Blueprint $table): void {
            $table->timestampTz('retired_at')->nullable()->after('sequence');
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropUnique('lessons_module_sequence_unique');
        });
        DB::statement(
            'CREATE UNIQUE INDEX curriculum_levels_active_sequence_unique ON curriculum_levels (organization_id, curriculum_version_id, sequence) WHERE retired_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX curriculum_objectives_active_sequence_unique ON learning_objectives (organization_id, curriculum_version_id, sequence) WHERE retired_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX curriculum_modules_active_level_sequence_unique ON curriculum_modules (organization_id, curriculum_version_id, curriculum_level_id, sequence) WHERE retired_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX curriculum_lessons_active_module_sequence_unique ON lessons (organization_id, curriculum_version_id, curriculum_module_id, sequence) WHERE retired_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX curriculum_items_active_lesson_sequence_unique ON learning_items (organization_id, curriculum_version_id, lesson_id, sequence) WHERE retired_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX curriculum_missions_active_sequence_unique ON curriculum_missions (organization_id, curriculum_version_id, sequence) WHERE retired_at IS NULL',
        );

        Schema::create('content_workspace_commands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('actor_membership_id');
            $table->string('idempotency_key', 128);
            $table->string('operation', 100);
            $table->string('target_type', 64);
            $table->ulid('target_id');
            $table->char('request_fingerprint', 64);
            $table->string('result_type', 64)->nullable();
            $table->ulid('result_id')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'idempotency_key'],
                'content_workspace_commands_idempotency_unique',
            );
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'content_workspace_commands_actor_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'target_type', 'target_id', 'created_at'],
                'content_workspace_commands_target_index',
            );
        });

        Schema::create('curriculum_workspaces', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('source_curriculum_version_id')->nullable();
            $table->ulid('source_curriculum_publication_id')->nullable();
            $table->unsignedSmallInteger('graph_schema_version')->default(2);
            $table->string('state', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->ulid('current_draft_revision_id')->nullable();
            $table->ulid('submitted_graph_revision_id')->nullable();
            $table->boolean('legacy_level_assignment_required')->default(false);
            $table->ulid('created_by_membership_id');
            $table->timestampTz('sealed_at')->nullable();
            $table->timestampTz('abandoned_at')->nullable();
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'curriculum_version_id'],
                'curriculum_workspaces_primary',
            );
            $table->unique(
                ['organization_id', 'program_id', 'curriculum_version_id'],
                'curriculum_workspaces_program_version_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id', 'curriculum_version_id'],
                'curriculum_workspaces_version_tenant_fk',
            )->references(['organization_id', 'program_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'program_id', 'source_curriculum_version_id'],
                'curriculum_workspaces_source_version_fk',
            )->references(['organization_id', 'program_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'program_id',
                    'source_curriculum_version_id',
                    'source_curriculum_publication_id',
                ],
                'curriculum_workspaces_source_publication_fk',
            )->references([
                'organization_id',
                'program_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'created_by_membership_id'],
                'curriculum_workspaces_creator_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'state', 'updated_at', 'curriculum_version_id'],
                'curriculum_workspaces_state_index',
            );
        });

        Schema::create('curriculum_draft_graph_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->ulid('parent_revision_id')->nullable();
            $table->unsignedInteger('revision_number');
            $table->json('graph');
            $table->char('graph_hash', 64);
            $table->json('blocker_codes');
            $table->boolean('is_complete');
            $table->ulid('authored_by_membership_id');
            $table->timestampTz('authored_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'id'],
                'curriculum_draft_graph_revisions_version_id_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'revision_number'],
                'curriculum_draft_graph_revisions_number_unique',
            );
            $table->unique(
                ['organization_id', 'curriculum_version_id', 'graph_hash'],
                'curriculum_draft_graph_revisions_hash_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id', 'curriculum_version_id'],
                'curriculum_draft_graph_revisions_version_fk',
            )->references(['organization_id', 'program_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'parent_revision_id'],
                'curriculum_draft_graph_revisions_parent_fk',
            )->references(['organization_id', 'curriculum_version_id', 'id'])
                ->on('curriculum_draft_graph_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'authored_by_membership_id'],
                'curriculum_draft_graph_revisions_author_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::table('curriculum_workspaces', function (Blueprint $table): void {
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'current_draft_revision_id',
                ],
                'curriculum_workspaces_draft_head_fk',
            )->references(['organization_id', 'curriculum_version_id', 'id'])
                ->on('curriculum_draft_graph_revisions')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'submitted_graph_revision_id',
                ],
                'curriculum_workspaces_submitted_graph_fk',
            )->references(['organization_id', 'curriculum_version_id', 'id'])
                ->on('curriculum_graph_revisions')
                ->restrictOnDelete();
        });

        Schema::create('curriculum_workspace_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->string('event_type', 48);
            $table->unsignedInteger('workspace_version');
            $table->ulid('draft_revision_id')->nullable();
            $table->ulid('graph_revision_id')->nullable();
            $table->ulid('actor_membership_id');
            $table->ulid('command_id')->nullable();
            $table->string('reason_key', 100)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->foreign(
                ['organization_id', 'program_id', 'curriculum_version_id'],
                'curriculum_workspace_events_workspace_fk',
            )->references(['organization_id', 'program_id', 'curriculum_version_id'])
                ->on('curriculum_workspaces')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'draft_revision_id'],
                'curriculum_workspace_events_draft_revision_fk',
            )->references(['organization_id', 'curriculum_version_id', 'id'])
                ->on('curriculum_draft_graph_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'curriculum_version_id', 'graph_revision_id'],
                'curriculum_workspace_events_graph_revision_fk',
            )->references(['organization_id', 'curriculum_version_id', 'id'])
                ->on('curriculum_graph_revisions')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'curriculum_workspace_events_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'command_id'],
                'curriculum_workspace_events_command_fk',
            )->references(['organization_id', 'id'])
                ->on('content_workspace_commands')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'curriculum_version_id', 'occurred_at', 'id'],
                'curriculum_workspace_events_timeline_index',
            );
        });

        Schema::create('program_descriptor_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('program_id');
            $table->unsignedInteger('previous_version');
            $table->unsignedInteger('successor_version');
            $table->string('previous_internal_name', 160);
            $table->string('successor_internal_name', 160);
            $table->ulid('actor_membership_id');
            $table->ulid('command_id');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'program_id', 'successor_version'],
                'program_descriptor_events_version_unique',
            );
            $table->foreign(
                ['organization_id', 'program_id'],
                'program_descriptor_events_program_fk',
            )->references(['organization_id', 'id'])
                ->on('programs')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'program_descriptor_events_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'command_id'],
                'program_descriptor_events_command_fk',
            )->references(['organization_id', 'id'])
                ->on('content_workspace_commands')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'program_id', 'occurred_at', 'id'],
                'program_descriptor_events_timeline_index',
            );
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        if (
            Schema::hasTable('curriculum_workspaces')
            && DB::table('curriculum_workspaces')->exists()
        ) {
            throw new RuntimeException(
                'Cannot roll back ADMIN-CONTENT-01 while content workspaces exist.',
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->dropPostgresConstraints();
        }

        Schema::dropIfExists('program_descriptor_events');
        Schema::dropIfExists('curriculum_workspace_events');
        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::table('curriculum_workspaces', function (Blueprint $table): void {
                $table->dropForeign('curriculum_workspaces_submitted_graph_fk');
                $table->dropForeign('curriculum_workspaces_draft_head_fk');
            });
        }
        Schema::dropIfExists('curriculum_draft_graph_revisions');
        Schema::dropIfExists('curriculum_workspaces');
        Schema::dropIfExists('content_workspace_commands');

        DB::statement('DROP INDEX IF EXISTS curriculum_missions_active_sequence_unique');
        DB::statement('DROP INDEX IF EXISTS curriculum_items_active_lesson_sequence_unique');
        DB::statement('DROP INDEX IF EXISTS curriculum_lessons_active_module_sequence_unique');
        DB::statement('DROP INDEX IF EXISTS curriculum_modules_active_level_sequence_unique');
        DB::statement('DROP INDEX IF EXISTS curriculum_objectives_active_sequence_unique');
        DB::statement('DROP INDEX IF EXISTS curriculum_levels_active_sequence_unique');

        Schema::table('curriculum_missions', function (Blueprint $table): void {
            $table->dropColumn('retired_at');
        });
        Schema::table('learning_items', function (Blueprint $table): void {
            $table->dropColumn('retired_at');
        });
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('retired_at');
            $table->unique(
                [
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_module_id',
                    'sequence',
                ],
                'lessons_module_sequence_unique',
            );
        });
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->restoreSqliteCurriculumModules();
        } else {
            Schema::table('curriculum_modules', function (Blueprint $table): void {
                $table->dropForeign('curriculum_modules_level_tenant_fk');
                $table->dropColumn(['curriculum_level_id', 'retired_at']);
                $table->unique(
                    ['organization_id', 'curriculum_version_id', 'sequence'],
                    'curriculum_modules_version_sequence_unique',
                );
            });
        }
        Schema::table('learning_objectives', function (Blueprint $table): void {
            $table->dropColumn('retired_at');
        });
        Schema::dropIfExists('curriculum_levels');
        Schema::table('programs', function (Blueprint $table): void {
            $table->dropColumn('descriptor_version');
        });
    }

    private function restoreSqliteCurriculumModules(): void
    {
        $temporaryTable = 'curriculum_modules_admin_content_rollback';
        Schema::dropIfExists($temporaryTable);
        Schema::create($temporaryTable, function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id');
            $table->string('key', 100);
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->timestampsTz();
            $table->foreign(['organization_id', 'curriculum_version_id'])
                ->references(['organization_id', 'id'])
                ->on('curriculum_versions')
                ->restrictOnDelete();
        });
        $legacyColumns = [
            'id',
            'organization_id',
            'curriculum_version_id',
            'key',
            'sequence',
            'estimated_minutes',
            'created_at',
            'updated_at',
        ];
        DB::table($temporaryTable)->insertUsing(
            $legacyColumns,
            DB::table('curriculum_modules')->select($legacyColumns),
        );
        Schema::drop('curriculum_modules');
        Schema::rename($temporaryTable, 'curriculum_modules');
        Schema::table('curriculum_modules', function (Blueprint $table): void {
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
        });
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE programs ADD CONSTRAINT programs_descriptor_version_check CHECK (descriptor_version > 0)',
        );
        DB::statement(
            "ALTER TABLE curriculum_levels ADD CONSTRAINT curriculum_levels_values_check CHECK (sequence > 0 AND key ~ '^[a-z0-9][a-z0-9._-]{0,99}$')",
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE content_workspace_commands
ADD CONSTRAINT content_workspace_commands_values_check
CHECK (
    request_fingerprint ~ '^[0-9a-f]{64}$'
    AND operation ~ '^[a-z][a-z0-9_.-]{0,99}$'
    AND target_type ~ '^[a-z][a-z0-9_.-]{0,63}$'
    AND (
        (result_type IS NULL AND result_id IS NULL AND completed_at IS NULL)
        OR
        (result_type ~ '^[a-z][a-z0-9_.-]{0,63}$' AND result_id IS NOT NULL AND completed_at IS NOT NULL)
    )
)
SQL,
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE curriculum_workspaces
ADD CONSTRAINT curriculum_workspaces_values_check
CHECK (
    graph_schema_version = 2
    AND state IN ('draft', 'in_review', 'sealed', 'abandoned')
    AND version > 0
    AND (source_curriculum_publication_id IS NULL OR source_curriculum_version_id IS NOT NULL)
    AND ((state = 'sealed') = (sealed_at IS NOT NULL))
    AND ((state = 'abandoned') = (abandoned_at IS NOT NULL))
    AND NOT (sealed_at IS NOT NULL AND abandoned_at IS NOT NULL)
)
SQL,
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE curriculum_draft_graph_revisions
ADD CONSTRAINT curriculum_draft_graph_revisions_values_check
CHECK (
    revision_number > 0
    AND graph_hash ~ '^[0-9a-f]{64}$'
    AND jsonb_typeof(graph::jsonb) = 'object'
    AND jsonb_typeof(blocker_codes::jsonb) = 'array'
    AND ((revision_number = 1 AND parent_revision_id IS NULL) OR (revision_number > 1 AND parent_revision_id IS NOT NULL))
)
SQL,
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE curriculum_workspace_events
ADD CONSTRAINT curriculum_workspace_events_values_check
CHECK (
    event_type IN ('workspace.created', 'workspace.successor_created', 'workspace.graph_saved', 'workspace.review_submitted', 'workspace.reopened', 'workspace.sealed', 'workspace.abandoned', 'content.document_created')
    AND workspace_version > 0
    AND (reason_key IS NULL OR reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND occurred_at = created_at
)
SQL,
        );
        DB::statement(
            'ALTER TABLE program_descriptor_events ADD CONSTRAINT program_descriptor_events_values_check CHECK (previous_version > 0 AND successor_version = previous_version + 1 AND previous_internal_name <> successor_internal_name AND occurred_at = created_at)',
        );

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION admin_content_reject_append_only_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'admin content history is append-only';
END;
$$;

CREATE TRIGGER curriculum_draft_graph_revisions_append_only
BEFORE UPDATE OR DELETE ON curriculum_draft_graph_revisions
FOR EACH ROW EXECUTE FUNCTION admin_content_reject_append_only_mutation();

CREATE TRIGGER curriculum_workspace_events_append_only
BEFORE UPDATE OR DELETE ON curriculum_workspace_events
FOR EACH ROW EXECUTE FUNCTION admin_content_reject_append_only_mutation();

CREATE TRIGGER program_descriptor_events_append_only
BEFORE UPDATE OR DELETE ON program_descriptor_events
FOR EACH ROW EXECUTE FUNCTION admin_content_reject_append_only_mutation();

CREATE OR REPLACE FUNCTION admin_content_command_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.actor_membership_id IS DISTINCT FROM NEW.actor_membership_id
        OR OLD.idempotency_key IS DISTINCT FROM NEW.idempotency_key
        OR OLD.operation IS DISTINCT FROM NEW.operation
        OR OLD.target_type IS DISTINCT FROM NEW.target_type
        OR OLD.target_id IS DISTINCT FROM NEW.target_id
        OR OLD.request_fingerprint IS DISTINCT FROM NEW.request_fingerprint
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR OLD.completed_at IS NOT NULL
        OR NEW.completed_at IS NULL
        OR NEW.result_type IS NULL
        OR NEW.result_id IS NULL
    THEN
        RAISE EXCEPTION 'invalid admin content command transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER content_workspace_commands_transition
BEFORE UPDATE ON content_workspace_commands
FOR EACH ROW EXECUTE FUNCTION admin_content_command_transition();

CREATE TRIGGER content_workspace_commands_no_delete
BEFORE DELETE ON content_workspace_commands
FOR EACH ROW EXECUTE FUNCTION admin_content_reject_append_only_mutation();

CREATE OR REPLACE FUNCTION admin_content_workspace_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.program_id IS DISTINCT FROM NEW.program_id
        OR OLD.curriculum_version_id IS DISTINCT FROM NEW.curriculum_version_id
        OR OLD.source_curriculum_version_id IS DISTINCT FROM NEW.source_curriculum_version_id
        OR OLD.source_curriculum_publication_id IS DISTINCT FROM NEW.source_curriculum_publication_id
        OR OLD.graph_schema_version IS DISTINCT FROM NEW.graph_schema_version
        OR OLD.created_by_membership_id IS DISTINCT FROM NEW.created_by_membership_id
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR NEW.version <> OLD.version + 1
        OR OLD.state IN ('sealed', 'abandoned')
        OR (OLD.state = 'draft' AND NEW.state NOT IN ('draft', 'in_review', 'sealed', 'abandoned'))
        OR (OLD.state = 'in_review' AND NEW.state NOT IN ('draft', 'in_review', 'sealed', 'abandoned'))
    THEN
        RAISE EXCEPTION 'invalid curriculum workspace transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER curriculum_workspaces_transition
BEFORE UPDATE ON curriculum_workspaces
FOR EACH ROW EXECUTE FUNCTION admin_content_workspace_transition();

CREATE TRIGGER curriculum_workspaces_no_delete
BEFORE DELETE ON curriculum_workspaces
FOR EACH ROW EXECUTE FUNCTION admin_content_reject_append_only_mutation();

CREATE OR REPLACE FUNCTION admin_content_program_descriptor_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.key IS DISTINCT FROM NEW.key
        OR OLD.created_by_membership_id IS DISTINCT FROM NEW.created_by_membership_id
        OR OLD.created_by_attribution IS DISTINCT FROM NEW.created_by_attribution
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR (
            OLD.internal_name IS DISTINCT FROM NEW.internal_name
            AND NEW.descriptor_version <> OLD.descriptor_version + 1
        )
        OR (
            OLD.internal_name IS NOT DISTINCT FROM NEW.internal_name
            AND NEW.descriptor_version <> OLD.descriptor_version
        )
    THEN
        RAISE EXCEPTION 'invalid program descriptor transition';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER programs_descriptor_transition
BEFORE UPDATE ON programs
FOR EACH ROW EXECUTE FUNCTION admin_content_program_descriptor_transition();
SQL);

        DB::statement(
            'ALTER TABLE publication_events DROP CONSTRAINT publication_events_type_check',
        );
        DB::statement(
            "ALTER TABLE publication_events ADD CONSTRAINT publication_events_type_check CHECK (event_type IN ('scheduled', 'schedule_cancelled', 'activated', 'retired', 'rollback_activated', 'suspended', 'resumed'))",
        );
    }

    private function dropPostgresConstraints(): void
    {
        DB::statement(
            'ALTER TABLE publication_events DROP CONSTRAINT publication_events_type_check',
        );
        DB::statement(
            "ALTER TABLE publication_events ADD CONSTRAINT publication_events_type_check CHECK (event_type IN ('scheduled', 'activated', 'retired', 'rollback_activated', 'suspended', 'resumed'))",
        );
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS programs_descriptor_transition ON programs;
DROP TRIGGER IF EXISTS curriculum_workspaces_no_delete ON curriculum_workspaces;
DROP TRIGGER IF EXISTS curriculum_workspaces_transition ON curriculum_workspaces;
DROP TRIGGER IF EXISTS content_workspace_commands_no_delete ON content_workspace_commands;
DROP TRIGGER IF EXISTS content_workspace_commands_transition ON content_workspace_commands;
DROP TRIGGER IF EXISTS program_descriptor_events_append_only ON program_descriptor_events;
DROP TRIGGER IF EXISTS curriculum_workspace_events_append_only ON curriculum_workspace_events;
DROP TRIGGER IF EXISTS curriculum_draft_graph_revisions_append_only ON curriculum_draft_graph_revisions;
DROP FUNCTION IF EXISTS admin_content_program_descriptor_transition();
DROP FUNCTION IF EXISTS admin_content_workspace_transition();
DROP FUNCTION IF EXISTS admin_content_command_transition();
DROP FUNCTION IF EXISTS admin_content_reject_append_only_mutation();
SQL);
    }
};
