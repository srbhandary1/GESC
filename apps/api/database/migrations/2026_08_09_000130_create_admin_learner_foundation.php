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
        if ($this->hasDuplicateCurrentEnrollments()) {
            throw new RuntimeException(
                'Cannot enable learner administration while a learner has more than one current enrollment.',
            );
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('status');
        });

        Schema::create('learner_profile_current_goals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_profile_id');
            $table->ulid('learner_membership_id');
            $table->string('goal_key', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('corrected_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'learner_membership_id'],
                'learner_current_goals_membership_unique',
            );
            $table->foreign(
                [
                    'organization_id',
                    'learner_profile_id',
                    'learner_membership_id',
                ],
                'learner_current_goals_profile_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('learner_profiles')
                ->restrictOnDelete();
        });

        $this->backfillCurrentGoals();

        Schema::create('learner_goal_corrections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_profile_id');
            $table->ulid('learner_membership_id');
            $table->ulid('actor_membership_id');
            $table->ulid('command_id');
            $table->string('previous_goal_key', 64);
            $table->string('successor_goal_key', 64);
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'id']);
            $table->foreign(
                [
                    'organization_id',
                    'learner_profile_id',
                    'learner_membership_id',
                ],
                'learner_goal_corrections_profile_fk',
            )->references([
                'organization_id',
                'id',
                'learner_membership_id',
            ])->on('learner_profiles')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'actor_membership_id'],
                'learner_goal_corrections_actor_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'command_id'],
                'learner_goal_corrections_command_fk',
            )->references(['organization_id', 'id'])
                ->on('organization_administration_commands')
                ->restrictOnDelete();
            $table->index(
                [
                    'organization_id',
                    'learner_membership_id',
                    'effective_at',
                    'id',
                ],
                'learner_goal_corrections_timeline_idx',
            );
        });

        Schema::table('organization_administration_events', function (Blueprint $table): void {
            $table->ulid('subject_learner_profile_id')->nullable()
                ->after('subject_invitation_id');
            $table->ulid('subject_enrollment_id')->nullable()
                ->after('subject_learner_profile_id');
            $table->foreign(
                ['organization_id', 'subject_learner_profile_id'],
                'organization_admin_events_learner_profile_fk',
            )->references(['organization_id', 'id'])
                ->on('learner_profiles')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'subject_enrollment_id'],
                'organization_admin_events_enrollment_fk',
            )->references(['organization_id', 'id'])
                ->on('enrollments')
                ->restrictOnDelete();
            $table->index(
                [
                    'organization_id',
                    'subject_learner_profile_id',
                    'effective_at',
                    'id',
                ],
                'organization_admin_events_learner_profile_idx',
            );
            $table->index(
                [
                    'organization_id',
                    'subject_enrollment_id',
                    'effective_at',
                    'id',
                ],
                'organization_admin_events_enrollment_idx',
            );
        });

        $this->createOneCurrentEnrollmentIndex();
        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::dropIfExists('learner_goal_corrections');
            Schema::dropIfExists('learner_profile_current_goals');
            $this->rebuildLegacyAdministrationEventsForSqlite();
            $this->dropOneCurrentEnrollmentIndex();
            Schema::table('enrollments', function (Blueprint $table): void {
                $table->dropColumn('version');
            });

            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE organization_administration_events DROP CONSTRAINT IF EXISTS organization_admin_events_target_check',
            );
            DB::statement(
                'DROP TRIGGER IF EXISTS learner_goal_corrections_append_only ON learner_goal_corrections',
            );
            DB::statement(
                'DROP TRIGGER IF EXISTS learner_current_goals_transition ON learner_profile_current_goals',
            );
            DB::statement(
                'DROP FUNCTION IF EXISTS learner_enforce_current_goal_transition()',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS enrollments_admin_version_check',
            );
            $this->installLegacyEnrollmentTrigger();
        }

        Schema::table('organization_administration_events', function (Blueprint $table): void {
            $table->dropForeign('organization_admin_events_enrollment_fk');
            $table->dropForeign('organization_admin_events_learner_profile_fk');
            $table->dropIndex('organization_admin_events_enrollment_idx');
            $table->dropIndex('organization_admin_events_learner_profile_idx');
            $table->dropColumn([
                'subject_enrollment_id',
                'subject_learner_profile_id',
            ]);
        });

        Schema::dropIfExists('learner_goal_corrections');
        Schema::dropIfExists('learner_profile_current_goals');

        $this->dropOneCurrentEnrollmentIndex();
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('version');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->addLegacyAdministrationEventConstraint();
        }
    }

    private function rebuildLegacyAdministrationEventsForSqlite(): void
    {
        $temporary = 'organization_administration_events_admin_learner_rollback';
        Schema::dropIfExists($temporary);
        Schema::create($temporary, function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('actor_membership_id');
            $table->string('event_type', 80);
            $table->ulid('subject_membership_id')->nullable();
            $table->ulid('subject_role_id')->nullable();
            $table->ulid('subject_cohort_id')->nullable();
            $table->ulid('subject_branch_id')->nullable();
            $table->ulid('subject_invitation_id')->nullable();
            $table->ulid('permission_id')->nullable();
            $table->ulid('command_id')->nullable();
            $table->string('reason_key', 100)->nullable();
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at');

            $table->foreign('organization_id')
                ->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign(['organization_id', 'actor_membership_id'])
                ->references(['organization_id', 'id'])
                ->on('memberships')->restrictOnDelete();
            $table->foreign(['organization_id', 'subject_membership_id'])
                ->references(['organization_id', 'id'])
                ->on('memberships')->restrictOnDelete();
            $table->foreign(['organization_id', 'subject_role_id'])
                ->references(['organization_id', 'id'])
                ->on('roles')->restrictOnDelete();
            $table->foreign(['organization_id', 'subject_cohort_id'])
                ->references(['organization_id', 'id'])
                ->on('cohorts')->restrictOnDelete();
            $table->foreign(['organization_id', 'subject_branch_id'])
                ->references(['organization_id', 'id'])
                ->on('branches')->restrictOnDelete();
            $table->foreign(['organization_id', 'subject_invitation_id'])
                ->references(['organization_id', 'id'])
                ->on('institution_invites')->restrictOnDelete();
            $table->foreign('permission_id')
                ->references('id')->on('permissions')->restrictOnDelete();
            $table->foreign(['organization_id', 'command_id'])
                ->references(['organization_id', 'id'])
                ->on('organization_administration_commands')->restrictOnDelete();
        });
        $columns = [
            'id',
            'organization_id',
            'actor_membership_id',
            'event_type',
            'subject_membership_id',
            'subject_role_id',
            'subject_cohort_id',
            'subject_branch_id',
            'subject_invitation_id',
            'permission_id',
            'command_id',
            'reason_key',
            'effective_at',
            'created_at',
        ];
        DB::table($temporary)->insertUsing(
            $columns,
            DB::table('organization_administration_events')->select($columns),
        );
        Schema::drop('organization_administration_events');
        Schema::rename($temporary, 'organization_administration_events');
        Schema::table('organization_administration_events', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id']);
            $table->index(
                ['organization_id', 'event_type', 'effective_at', 'id'],
                'organization_admin_events_timeline_idx',
            );
            $table->index(
                ['organization_id', 'subject_membership_id', 'effective_at', 'id'],
                'organization_admin_events_membership_idx',
            );
            $table->index(
                ['organization_id', 'subject_role_id', 'effective_at', 'id'],
                'organization_admin_events_role_idx',
            );
        });
    }

    private function hasDuplicateCurrentEnrollments(): bool
    {
        return DB::table('enrollments')
            ->select(['organization_id', 'learner_membership_id'])
            ->whereIn('status', ['pending_curriculum', 'active'])
            ->groupBy('organization_id', 'learner_membership_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function backfillCurrentGoals(): void
    {
        DB::table('learner_goals')
            ->orderBy('id')
            ->eachById(static function (object $goal): void {
                DB::table('learner_profile_current_goals')->insert([
                    'id' => (string) $goal->id,
                    'organization_id' => (string) $goal->organization_id,
                    'learner_profile_id' => (string) $goal->learner_profile_id,
                    'learner_membership_id' => (string) $goal->learner_membership_id,
                    'goal_key' => (string) $goal->goal_key,
                    'version' => 1,
                    'corrected_at' => null,
                    'created_at' => $goal->created_at,
                    'updated_at' => $goal->created_at,
                ]);
            });
    }

    private function createOneCurrentEnrollmentIndex(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX enrollments_one_current_unique ON enrollments (organization_id, learner_membership_id) WHERE status IN ('pending_curriculum', 'active')",
        );
    }

    private function dropOneCurrentEnrollmentIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS enrollments_one_current_unique');
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE enrollments ADD CONSTRAINT enrollments_admin_version_check CHECK (version > 0)',
        );
        DB::statement(
            "ALTER TABLE learner_profile_current_goals ADD CONSTRAINT learner_current_goals_values_check CHECK (version > 0 AND goal_key IN ('everyday-conversation', 'workplace-confidence', 'travel-confidence'))",
        );
        DB::statement(
            "ALTER TABLE learner_goal_corrections ADD CONSTRAINT learner_goal_corrections_values_check CHECK (previous_goal_key IN ('everyday-conversation', 'workplace-confidence', 'travel-confidence') AND successor_goal_key IN ('everyday-conversation', 'workplace-confidence', 'travel-confidence') AND previous_goal_key <> successor_goal_key AND effective_at = created_at)",
        );

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION learner_enforce_current_goal_transition()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.learner_profile_id IS DISTINCT FROM NEW.learner_profile_id
        OR OLD.learner_membership_id IS DISTINCT FROM NEW.learner_membership_id
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
        OR OLD.goal_key IS NOT DISTINCT FROM NEW.goal_key
        OR NEW.version <> OLD.version + 1
        OR NEW.corrected_at IS NULL
        OR NEW.updated_at IS DISTINCT FROM NEW.corrected_at
        OR NOT EXISTS (
            SELECT 1
            FROM learner_goal_corrections AS correction
            WHERE correction.organization_id = NEW.organization_id
              AND correction.learner_profile_id = NEW.learner_profile_id
              AND correction.learner_membership_id = NEW.learner_membership_id
              AND correction.previous_goal_key = OLD.goal_key
              AND correction.successor_goal_key = NEW.goal_key
              AND correction.effective_at = NEW.corrected_at
              AND correction.created_at = NEW.corrected_at
        )
    THEN
        RAISE EXCEPTION 'Learner current-goal transition is not allowed'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
        DB::statement(
            'CREATE TRIGGER learner_current_goals_transition BEFORE UPDATE OR DELETE ON learner_profile_current_goals FOR EACH ROW EXECUTE FUNCTION learner_enforce_current_goal_transition()',
        );
        DB::statement(
            'CREATE TRIGGER learner_goal_corrections_append_only BEFORE UPDATE OR DELETE ON learner_goal_corrections FOR EACH ROW EXECUTE FUNCTION learning_reject_append_only_mutation()',
        );

        $this->installVersionedEnrollmentTrigger();
        DB::statement(
            'ALTER TABLE organization_administration_events DROP CONSTRAINT organization_admin_events_target_check',
        );
        $this->addLearnerAdministrationEventConstraint();
    }

    private function installVersionedEnrollmentTrigger(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION enrollment_enforce_pin_immutability()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    pin_changed boolean;
    transition_allowed boolean;
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.learner_membership_id IS DISTINCT FROM NEW.learner_membership_id
        OR OLD.enrolled_at IS DISTINCT FROM NEW.enrolled_at
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
    THEN
        RAISE EXCEPTION 'Enrollment identity is immutable'
            USING ERRCODE = '55000';
    END IF;

    transition_allowed :=
        OLD.status = NEW.status
        OR (OLD.status = 'pending_curriculum' AND NEW.status IN ('active', 'withdrawn'))
        OR (OLD.status = 'active' AND NEW.status IN ('completed', 'withdrawn'));

    IF NOT transition_allowed THEN
        RAISE EXCEPTION 'Enrollment transition is not allowed'
            USING ERRCODE = '55000';
    END IF;

    pin_changed :=
        OLD.curriculum_owner_organization_id IS DISTINCT FROM NEW.curriculum_owner_organization_id
        OR OLD.curriculum_version_id IS DISTINCT FROM NEW.curriculum_version_id
        OR OLD.curriculum_publication_id IS DISTINCT FROM NEW.curriculum_publication_id
        OR OLD.curriculum_share_grant_id IS DISTINCT FROM NEW.curriculum_share_grant_id
        OR OLD.activated_at IS DISTINCT FROM NEW.activated_at;

    IF pin_changed AND NOT (
        OLD.status = 'pending_curriculum'
        AND NEW.status = 'active'
        AND OLD.curriculum_owner_organization_id IS NULL
        AND OLD.curriculum_version_id IS NULL
        AND OLD.curriculum_publication_id IS NULL
        AND OLD.curriculum_share_grant_id IS NULL
        AND OLD.activated_at IS NULL
        AND NEW.curriculum_owner_organization_id IS NOT NULL
        AND NEW.curriculum_version_id IS NOT NULL
        AND NEW.curriculum_publication_id IS NOT NULL
        AND NEW.activated_at IS NOT NULL
    ) THEN
        RAISE EXCEPTION 'Enrollment curriculum pin is immutable'
            USING ERRCODE = '55000';
    END IF;

    IF NEW.version <> OLD.version + 1 THEN
        RAISE EXCEPTION 'Enrollment version transition is not allowed'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
    }

    private function installLegacyEnrollmentTrigger(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION enrollment_enforce_pin_immutability()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    pin_changed boolean;
    transition_allowed boolean;
BEGIN
    IF OLD.organization_id IS DISTINCT FROM NEW.organization_id THEN
        RAISE EXCEPTION 'Enrollment organization ownership is immutable'
            USING ERRCODE = '55000';
    END IF;

    transition_allowed :=
        OLD.status = NEW.status
        OR (OLD.status = 'pending_curriculum' AND NEW.status IN ('active', 'withdrawn'))
        OR (OLD.status = 'active' AND NEW.status IN ('completed', 'withdrawn'));

    IF NOT transition_allowed THEN
        RAISE EXCEPTION 'Enrollment status transition is not allowed'
            USING ERRCODE = '55000';
    END IF;

    pin_changed :=
        OLD.curriculum_owner_organization_id IS DISTINCT FROM NEW.curriculum_owner_organization_id
        OR OLD.curriculum_version_id IS DISTINCT FROM NEW.curriculum_version_id
        OR OLD.curriculum_publication_id IS DISTINCT FROM NEW.curriculum_publication_id
        OR OLD.curriculum_share_grant_id IS DISTINCT FROM NEW.curriculum_share_grant_id
        OR OLD.activated_at IS DISTINCT FROM NEW.activated_at;

    IF pin_changed AND NOT (
        OLD.status = 'pending_curriculum'
        AND NEW.status = 'active'
        AND OLD.curriculum_owner_organization_id IS NULL
        AND OLD.curriculum_version_id IS NULL
        AND OLD.curriculum_publication_id IS NULL
        AND OLD.curriculum_share_grant_id IS NULL
        AND OLD.activated_at IS NULL
        AND NEW.curriculum_owner_organization_id IS NOT NULL
        AND NEW.curriculum_version_id IS NOT NULL
        AND NEW.curriculum_publication_id IS NOT NULL
        AND NEW.activated_at IS NOT NULL
    ) THEN
        RAISE EXCEPTION 'Enrollment curriculum pin is immutable'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
    }

    private function addLearnerAdministrationEventConstraint(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE organization_administration_events
ADD CONSTRAINT organization_admin_events_target_check
CHECK (
    (reason_key IS NULL OR reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND (
        (
            event_type IN ('branch.created', 'branch.updated', 'branch.archived')
            AND subject_branch_id IS NOT NULL
            AND subject_membership_id IS NULL AND subject_role_id IS NULL
            AND subject_cohort_id IS NULL AND subject_invitation_id IS NULL
            AND subject_learner_profile_id IS NULL AND subject_enrollment_id IS NULL
            AND permission_id IS NULL
        )
        OR (
            event_type IN ('cohort.created', 'cohort.updated', 'cohort.archived')
            AND subject_cohort_id IS NOT NULL
            AND subject_membership_id IS NULL AND subject_role_id IS NULL
            AND subject_branch_id IS NULL AND subject_invitation_id IS NULL
            AND subject_learner_profile_id IS NULL AND subject_enrollment_id IS NULL
            AND permission_id IS NULL
        )
        OR (
            event_type IN ('invitation.issued', 'invitation.revoked', 'learner_invitation.issued', 'learner_invitation.revoked')
            AND subject_invitation_id IS NOT NULL
            AND subject_membership_id IS NULL AND subject_role_id IS NULL
            AND subject_cohort_id IS NULL AND subject_branch_id IS NULL
            AND subject_learner_profile_id IS NULL AND subject_enrollment_id IS NULL
            AND permission_id IS NULL
        )
        OR (
            event_type IN ('role.created', 'role.updated', 'role.retired')
            AND subject_role_id IS NOT NULL
            AND subject_membership_id IS NULL AND subject_cohort_id IS NULL
            AND subject_branch_id IS NULL AND subject_invitation_id IS NULL
            AND subject_learner_profile_id IS NULL AND subject_enrollment_id IS NULL
            AND permission_id IS NULL
        )
        OR (
            event_type IN ('role.permission_granted', 'role.permission_revoked')
            AND subject_role_id IS NOT NULL AND permission_id IS NOT NULL
            AND subject_membership_id IS NULL AND subject_cohort_id IS NULL
            AND subject_branch_id IS NULL AND subject_invitation_id IS NULL
            AND subject_learner_profile_id IS NULL AND subject_enrollment_id IS NULL
        )
        OR (
            event_type IN ('membership.role_assigned', 'membership.role_revoked')
            AND subject_membership_id IS NOT NULL AND subject_role_id IS NOT NULL
            AND subject_cohort_id IS NULL AND subject_branch_id IS NULL
            AND subject_invitation_id IS NULL AND subject_learner_profile_id IS NULL
            AND subject_enrollment_id IS NULL AND permission_id IS NULL
        )
        OR (
            event_type IN ('membership.suspended', 'membership.reactivated', 'membership_session.revoked', 'learner_membership.suspended', 'learner_membership.reactivated')
            AND subject_membership_id IS NOT NULL
            AND subject_role_id IS NULL AND subject_cohort_id IS NULL
            AND subject_branch_id IS NULL AND subject_invitation_id IS NULL
            AND subject_learner_profile_id IS NULL AND subject_enrollment_id IS NULL
            AND permission_id IS NULL
        )
        OR (
            event_type IN ('trainer.cohort_assigned', 'trainer.cohort_revoked')
            AND subject_membership_id IS NOT NULL AND subject_cohort_id IS NOT NULL
            AND subject_role_id IS NULL AND subject_branch_id IS NULL
            AND subject_invitation_id IS NULL AND subject_learner_profile_id IS NULL
            AND subject_enrollment_id IS NULL AND permission_id IS NULL
        )
        OR (
            event_type = 'learner_goal.corrected'
            AND subject_membership_id IS NOT NULL
            AND subject_learner_profile_id IS NOT NULL
            AND subject_role_id IS NULL AND subject_cohort_id IS NULL
            AND subject_branch_id IS NULL AND subject_invitation_id IS NULL
            AND subject_enrollment_id IS NULL AND permission_id IS NULL
        )
        OR (
            event_type IN ('learner_enrollment.created', 'learner_enrollment.cohort_changed', 'learner_enrollment.curriculum_activated', 'learner_enrollment.withdrawn')
            AND subject_membership_id IS NOT NULL
            AND subject_enrollment_id IS NOT NULL
            AND subject_role_id IS NULL AND subject_branch_id IS NULL
            AND subject_invitation_id IS NULL AND subject_learner_profile_id IS NULL
            AND permission_id IS NULL
        )
        OR (
            event_type = 'learner_membership.role_removed'
            AND subject_membership_id IS NOT NULL AND subject_role_id IS NOT NULL
            AND subject_cohort_id IS NULL AND subject_branch_id IS NULL
            AND subject_invitation_id IS NULL AND subject_learner_profile_id IS NULL
            AND subject_enrollment_id IS NULL AND permission_id IS NULL
        )
    )
)
SQL);
    }

    private function addLegacyAdministrationEventConstraint(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE organization_administration_events
ADD CONSTRAINT organization_admin_events_target_check
CHECK (
    (reason_key IS NULL OR reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND (
        (event_type IN ('branch.created', 'branch.updated', 'branch.archived') AND subject_branch_id IS NOT NULL AND subject_membership_id IS NULL AND subject_role_id IS NULL AND subject_cohort_id IS NULL AND subject_invitation_id IS NULL AND permission_id IS NULL)
        OR (event_type IN ('cohort.created', 'cohort.updated', 'cohort.archived') AND subject_cohort_id IS NOT NULL AND subject_membership_id IS NULL AND subject_role_id IS NULL AND subject_branch_id IS NULL AND subject_invitation_id IS NULL AND permission_id IS NULL)
        OR (event_type IN ('invitation.issued', 'invitation.revoked') AND subject_invitation_id IS NOT NULL AND subject_membership_id IS NULL AND subject_role_id IS NULL AND subject_cohort_id IS NULL AND subject_branch_id IS NULL AND permission_id IS NULL)
        OR (event_type IN ('role.created', 'role.updated', 'role.retired') AND subject_role_id IS NOT NULL AND subject_membership_id IS NULL AND subject_cohort_id IS NULL AND subject_branch_id IS NULL AND subject_invitation_id IS NULL AND permission_id IS NULL)
        OR (event_type IN ('role.permission_granted', 'role.permission_revoked') AND subject_role_id IS NOT NULL AND permission_id IS NOT NULL AND subject_membership_id IS NULL AND subject_cohort_id IS NULL AND subject_branch_id IS NULL AND subject_invitation_id IS NULL)
        OR (event_type IN ('membership.role_assigned', 'membership.role_revoked') AND subject_membership_id IS NOT NULL AND subject_role_id IS NOT NULL AND subject_cohort_id IS NULL AND subject_branch_id IS NULL AND subject_invitation_id IS NULL AND permission_id IS NULL)
        OR (event_type IN ('membership.suspended', 'membership.reactivated', 'membership_session.revoked') AND subject_membership_id IS NOT NULL AND subject_role_id IS NULL AND subject_cohort_id IS NULL AND subject_branch_id IS NULL AND subject_invitation_id IS NULL AND permission_id IS NULL)
        OR (event_type IN ('trainer.cohort_assigned', 'trainer.cohort_revoked') AND subject_membership_id IS NOT NULL AND subject_cohort_id IS NOT NULL AND subject_role_id IS NULL AND subject_branch_id IS NULL AND subject_invitation_id IS NULL AND permission_id IS NULL)
    )
)
SQL);
    }
};
