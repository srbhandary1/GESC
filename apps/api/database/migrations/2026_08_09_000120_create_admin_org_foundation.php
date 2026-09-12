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
        Schema::create('branches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('key', 100);
            $table->string('name', 160);
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'key']);
            $table->index(
                ['organization_id', 'status', 'name', 'id'],
                'branches_organization_status_name_idx',
            );
        });

        Schema::table('cohorts', function (Blueprint $table): void {
            $table->ulid('branch_id')->nullable()->after('organization_id');
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->timestampTz('archived_at')->nullable()->after('version');
            $table->foreign(
                ['organization_id', 'branch_id'],
                'cohorts_branch_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'branch_id', 'status', 'id'],
                'cohorts_organization_branch_status_idx',
            );
        });
        DB::table('cohorts')
            ->where('status', 'archived')
            ->whereNull('archived_at')
            ->update(['archived_at' => DB::raw('updated_at')]);

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('status', 24)->default('active')->after('is_system');
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->timestampTz('retired_at')->nullable()->after('version');
            $table->index(
                ['organization_id', 'status', 'name', 'id'],
                'roles_organization_status_name_idx',
            );
        });

        Schema::table('memberships', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('ended_at');
        });

        Schema::table('institution_invites', function (Blueprint $table): void {
            $table->ulid('issued_by_membership_id')->nullable()
                ->after('membership_type');
            $table->ulid('revoked_by_membership_id')->nullable()
                ->after('accepted_at');
            $table->timestampTz('revoked_at')->nullable()
                ->after('revoked_by_membership_id');
            $table->timestampTz('code_discard_after')->nullable()
                ->after('revoked_at');
            $table->unsignedInteger('version')->default(1)
                ->after('code_discard_after');
            $table->foreign(
                ['organization_id', 'issued_by_membership_id'],
                'institution_invites_issuer_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'revoked_by_membership_id'],
                'institution_invites_revoker_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                ['organization_id', 'status', 'code_discard_after', 'id'],
                'institution_invites_digest_cleanup_idx',
            );
        });
        $this->makeInviteDigestNullable();
        $this->backfillInviteLifecycleTimes();

        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->ulid('selected_organization_id')->nullable()
                ->after('user_id');
            $table->ulid('selected_membership_id')->nullable()
                ->after('selected_organization_id');
            $table->foreign(
                [
                    'selected_organization_id',
                    'user_id',
                    'selected_membership_id',
                ],
                'auth_sessions_selected_membership_identity_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(
                [
                    'selected_organization_id',
                    'selected_membership_id',
                    'revoked_at',
                    'absolute_expires_at',
                ],
                'auth_sessions_selected_membership_active_idx',
            );
        });

        Schema::create(
            'organization_administration_commands',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')
                    ->constrained('organizations')
                    ->restrictOnDelete();
                $table->ulid('actor_membership_id');
                $table->string('idempotency_key', 36);
                $table->string('operation', 100);
                $table->string('target_type', 64);
                $table->ulid('target_id');
                $table->char('request_fingerprint', 64);
                $table->string('result_type', 64)->nullable();
                $table->ulid('result_id')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestampTz('expires_at')->nullable();
                $table->timestampTz('created_at');

                $table->unique(['organization_id', 'id']);
                $table->unique(
                    ['organization_id', 'idempotency_key'],
                    'organization_admin_commands_idempotency_unique',
                );
                $table->foreign(
                    ['organization_id', 'actor_membership_id'],
                    'organization_admin_commands_actor_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('memberships')
                    ->restrictOnDelete();
                $table->index(
                    ['organization_id', 'expires_at', 'id'],
                    'organization_admin_commands_expiry_idx',
                );
            },
        );

        Schema::create(
            'organization_administration_events',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')
                    ->constrained('organizations')
                    ->restrictOnDelete();
                $table->ulid('actor_membership_id');
                $table->string('event_type', 80);
                $table->ulid('subject_membership_id')->nullable();
                $table->ulid('subject_role_id')->nullable();
                $table->ulid('subject_cohort_id')->nullable();
                $table->ulid('subject_branch_id')->nullable();
                $table->ulid('subject_invitation_id')->nullable();
                $table->foreignUlid('permission_id')->nullable()
                    ->constrained('permissions')
                    ->restrictOnDelete();
                $table->ulid('command_id')->nullable();
                $table->string('reason_key', 100)->nullable();
                $table->timestampTz('effective_at');
                $table->timestampTz('created_at');

                $table->unique(['organization_id', 'id']);
                $table->foreign(
                    ['organization_id', 'actor_membership_id'],
                    'organization_admin_events_actor_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('memberships')
                    ->restrictOnDelete();
                $table->foreign(
                    ['organization_id', 'subject_membership_id'],
                    'organization_admin_events_membership_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('memberships')
                    ->restrictOnDelete();
                $table->foreign(
                    ['organization_id', 'subject_role_id'],
                    'organization_admin_events_role_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('roles')
                    ->restrictOnDelete();
                $table->foreign(
                    ['organization_id', 'subject_cohort_id'],
                    'organization_admin_events_cohort_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('cohorts')
                    ->restrictOnDelete();
                $table->foreign(
                    ['organization_id', 'subject_branch_id'],
                    'organization_admin_events_branch_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('branches')
                    ->restrictOnDelete();
                $table->foreign(
                    ['organization_id', 'subject_invitation_id'],
                    'organization_admin_events_invitation_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('institution_invites')
                    ->restrictOnDelete();
                $table->foreign(
                    ['organization_id', 'command_id'],
                    'organization_admin_events_command_tenant_fk',
                )->references(['organization_id', 'id'])
                    ->on('organization_administration_commands')
                    ->restrictOnDelete();
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
            },
        );

        Schema::create(
            'authentication_session_cleanup_requests',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->ulid('authentication_session_id');
                $table->string('status', 24)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestampTz('available_at');
                $table->timestampTz('leased_until')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestampsTz();

                $table->index(
                    ['status', 'available_at', 'id'],
                    'auth_session_cleanup_due_idx',
                );
                $table->unique(
                    'authentication_session_id',
                    'auth_session_cleanup_session_unique',
                );
                $table->foreign(
                    'authentication_session_id',
                    'auth_session_cleanup_session_fk',
                )->references('id')
                    ->on('authentication_sessions')
                    ->cascadeOnDelete();
            },
        );

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_session_cleanup_requests');
        Schema::dropIfExists('organization_administration_events');
        Schema::dropIfExists('organization_administration_commands');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE authentication_sessions DROP CONSTRAINT IF EXISTS auth_sessions_selected_membership_pair_check',
            );
        }
        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->dropForeign('auth_sessions_selected_membership_identity_fk');
            $table->dropIndex('auth_sessions_selected_membership_active_idx');
            $table->dropColumn([
                'selected_organization_id',
                'selected_membership_id',
            ]);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE institution_invites DROP CONSTRAINT IF EXISTS institution_invites_admin_version_check',
            );
            DB::statement(
                'ALTER TABLE institution_invites DROP CONSTRAINT IF EXISTS institution_invites_revocation_check',
            );
            DB::statement(
                'ALTER TABLE institution_invites DROP CONSTRAINT IF EXISTS institution_invites_pending_digest_check',
            );
            DB::statement(
                'ALTER TABLE institution_invites DROP CONSTRAINT IF EXISTS institution_invites_operator_lifetime_check',
            );
        }
        $this->restoreInviteDigestsForRollback();
        Schema::table('institution_invites', function (Blueprint $table): void {
            $table->dropForeign('institution_invites_issuer_tenant_fk');
            $table->dropForeign('institution_invites_revoker_tenant_fk');
            $table->dropIndex('institution_invites_digest_cleanup_idx');
            $table->dropColumn([
                'issued_by_membership_id',
                'revoked_by_membership_id',
                'revoked_at',
                'code_discard_after',
                'version',
            ]);
        });
        $this->makeInviteDigestRequired();

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE memberships DROP CONSTRAINT IF EXISTS memberships_admin_version_check',
            );
        }
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn('version');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_admin_lifecycle_check',
            );
        }
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex('roles_organization_status_name_idx');
            $table->dropColumn(['status', 'version', 'retired_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE cohorts DROP CONSTRAINT IF EXISTS cohorts_admin_lifecycle_check',
            );
        }
        Schema::table('cohorts', function (Blueprint $table): void {
            $table->dropForeign('cohorts_branch_tenant_fk');
            $table->dropIndex('cohorts_organization_branch_status_idx');
            $table->dropColumn(['branch_id', 'version', 'archived_at']);
        });

        Schema::dropIfExists('branches');
    }

    private function makeInviteDigestNullable(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE institution_invites ALTER COLUMN code_digest DROP NOT NULL',
            );

            return;
        }

        Schema::table('institution_invites', function (Blueprint $table): void {
            $table->char('code_digest', 64)->nullable()->change();
        });
    }

    private function makeInviteDigestRequired(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE institution_invites ALTER COLUMN code_digest SET NOT NULL',
            );

            return;
        }

        Schema::table('institution_invites', function (Blueprint $table): void {
            $table->char('code_digest', 64)->nullable(false)->change();
        });
    }

    private function backfillInviteLifecycleTimes(): void
    {
        DB::table('institution_invites')
            ->where('status', 'accepted')
            ->whereNull('code_discard_after')
            ->update([
                'code_discard_after' => DB::raw('accepted_at'),
            ]);
        DB::table('institution_invites')
            ->where('status', 'revoked')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => DB::raw('updated_at'),
                'code_discard_after' => DB::raw('updated_at'),
            ]);
        DB::table('institution_invites')
            ->where('status', 'expired')
            ->whereNull('code_discard_after')
            ->update([
                'code_discard_after' => DB::raw('expires_at'),
            ]);
    }

    private function restoreInviteDigestsForRollback(): void
    {
        DB::table('institution_invites')
            ->whereNull('code_digest')
            ->orderBy('id')
            ->eachById(static function (object $invite): void {
                DB::table('institution_invites')
                    ->where('id', $invite->id)
                    ->update([
                        'code_digest' => hash(
                            'sha256',
                            "admin-org-rollback\0".(string) $invite->id,
                        ),
                    ]);
            });
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "ALTER TABLE branches ADD CONSTRAINT branches_admin_lifecycle_check CHECK (version > 0 AND ((status = 'active' AND archived_at IS NULL) OR (status = 'archived' AND archived_at IS NOT NULL)))",
        );
        DB::statement(
            "ALTER TABLE cohorts ADD CONSTRAINT cohorts_admin_lifecycle_check CHECK (version > 0 AND ((status = 'active' AND archived_at IS NULL) OR (status = 'archived' AND archived_at IS NOT NULL)))",
        );
        DB::statement(
            "ALTER TABLE roles ADD CONSTRAINT roles_admin_lifecycle_check CHECK (version > 0 AND ((status = 'active' AND retired_at IS NULL) OR (status = 'retired' AND retired_at IS NOT NULL)) AND (is_system = FALSE OR status = 'active'))",
        );
        DB::statement(
            'ALTER TABLE memberships ADD CONSTRAINT memberships_admin_version_check CHECK (version > 0)',
        );
        DB::statement(
            'ALTER TABLE institution_invites ADD CONSTRAINT institution_invites_admin_version_check CHECK (version > 0)',
        );
        DB::statement(
            "ALTER TABLE institution_invites ADD CONSTRAINT institution_invites_revocation_check CHECK ((status = 'revoked' AND revoked_at IS NOT NULL AND (issued_by_membership_id IS NULL OR revoked_by_membership_id IS NOT NULL)) OR (status <> 'revoked' AND revoked_by_membership_id IS NULL AND revoked_at IS NULL))",
        );
        DB::statement(
            "ALTER TABLE institution_invites ADD CONSTRAINT institution_invites_pending_digest_check CHECK (status <> 'pending' OR code_digest IS NOT NULL)",
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE institution_invites
ADD CONSTRAINT institution_invites_operator_lifetime_check
CHECK (
    issued_by_membership_id IS NULL
    OR expires_at IN (
        created_at + INTERVAL '24 hours',
        created_at + INTERVAL '72 hours',
        created_at + INTERVAL '168 hours'
    )
)
SQL,
        );
        DB::statement(
            'ALTER TABLE authentication_sessions ADD CONSTRAINT auth_sessions_selected_membership_pair_check CHECK ((selected_organization_id IS NULL AND selected_membership_id IS NULL) OR (selected_organization_id IS NOT NULL AND selected_membership_id IS NOT NULL))',
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE organization_administration_commands
ADD CONSTRAINT organization_admin_commands_values_check
CHECK (
    request_fingerprint ~ '^[0-9a-f]{64}$'
    AND operation ~ '^[a-z][a-z0-9_.-]{0,99}$'
    AND target_type ~ '^[a-z][a-z0-9_.-]{0,63}$'
    AND (
        result_type IS NULL
        OR result_type ~ '^[a-z][a-z0-9_.-]{0,63}$'
    )
    AND (
        (
            result_type IS NULL
            AND result_id IS NULL
            AND completed_at IS NULL
            AND expires_at IS NULL
        )
        OR (
            result_type IS NOT NULL
            AND result_id IS NOT NULL
            AND completed_at IS NOT NULL
            AND expires_at = completed_at + INTERVAL '365 days'
        )
    )
)
SQL,
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE organization_administration_events
ADD CONSTRAINT organization_admin_events_target_check
CHECK (
    (
        reason_key IS NULL
        OR reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$'
    )
    AND (
        (
        event_type IN ('branch.created', 'branch.updated', 'branch.archived')
        AND subject_branch_id IS NOT NULL
        AND subject_membership_id IS NULL
        AND subject_role_id IS NULL
        AND subject_cohort_id IS NULL
        AND subject_invitation_id IS NULL
        AND permission_id IS NULL
    )
    OR (
        event_type IN ('cohort.created', 'cohort.updated', 'cohort.archived')
        AND subject_cohort_id IS NOT NULL
        AND subject_membership_id IS NULL
        AND subject_role_id IS NULL
        AND subject_branch_id IS NULL
        AND subject_invitation_id IS NULL
        AND permission_id IS NULL
    )
    OR (
        event_type IN ('invitation.issued', 'invitation.revoked')
        AND subject_invitation_id IS NOT NULL
        AND subject_membership_id IS NULL
        AND subject_role_id IS NULL
        AND subject_cohort_id IS NULL
        AND subject_branch_id IS NULL
        AND permission_id IS NULL
    )
    OR (
        event_type IN ('role.created', 'role.updated', 'role.retired')
        AND subject_role_id IS NOT NULL
        AND subject_membership_id IS NULL
        AND subject_cohort_id IS NULL
        AND subject_branch_id IS NULL
        AND subject_invitation_id IS NULL
        AND permission_id IS NULL
    )
    OR (
        event_type IN ('role.permission_granted', 'role.permission_revoked')
        AND subject_role_id IS NOT NULL
        AND permission_id IS NOT NULL
        AND subject_membership_id IS NULL
        AND subject_cohort_id IS NULL
        AND subject_branch_id IS NULL
        AND subject_invitation_id IS NULL
    )
    OR (
        event_type IN ('membership.role_assigned', 'membership.role_revoked')
        AND subject_membership_id IS NOT NULL
        AND subject_role_id IS NOT NULL
        AND subject_cohort_id IS NULL
        AND subject_branch_id IS NULL
        AND subject_invitation_id IS NULL
        AND permission_id IS NULL
    )
    OR (
        event_type IN ('membership.suspended', 'membership.reactivated', 'membership_session.revoked')
        AND subject_membership_id IS NOT NULL
        AND subject_role_id IS NULL
        AND subject_cohort_id IS NULL
        AND subject_branch_id IS NULL
        AND subject_invitation_id IS NULL
        AND permission_id IS NULL
    )
    OR (
        event_type IN ('trainer.cohort_assigned', 'trainer.cohort_revoked')
        AND subject_membership_id IS NOT NULL
        AND subject_cohort_id IS NOT NULL
        AND subject_role_id IS NULL
        AND subject_branch_id IS NULL
        AND subject_invitation_id IS NULL
        AND permission_id IS NULL
        )
    )
)
SQL,
        );
        DB::statement(
            <<<'SQL'
ALTER TABLE authentication_session_cleanup_requests
ADD CONSTRAINT auth_session_cleanup_lifecycle_check
CHECK (
    attempts BETWEEN 0 AND 10
    AND (
        (
            status = 'pending'
            AND leased_until IS NULL
            AND completed_at IS NULL
        )
        OR (
            status = 'processing'
            AND leased_until IS NOT NULL
            AND completed_at IS NULL
        )
        OR (
            status = 'completed'
            AND leased_until IS NULL
            AND completed_at IS NOT NULL
        )
    )
)
SQL,
        );
    }
};
