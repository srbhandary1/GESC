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
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('role_id');
            $table->foreignUlid('permission_id')
                ->constrained('permissions')
                ->restrictOnDelete();
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'role_id', 'permission_id'],
                'role_permissions_primary',
            );
            $table->foreign(
                ['organization_id', 'role_id'],
                'role_permissions_role_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('roles')
                ->restrictOnDelete();
            $table->index(['organization_id', 'permission_id']);
        });

        Schema::create('membership_roles', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('membership_id');
            $table->ulid('role_id');
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'membership_id', 'role_id'],
                'membership_roles_primary',
            );
            $table->foreign(
                ['organization_id', 'membership_id'],
                'membership_roles_membership_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'role_id'],
                'membership_roles_role_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('roles')
                ->restrictOnDelete();
            $table->index(['organization_id', 'role_id']);
        });

        Schema::create('cohort_trainers', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('cohort_id');
            $table->ulid('trainer_membership_id');
            $table->timestampsTz();

            $table->primary(
                ['organization_id', 'cohort_id', 'trainer_membership_id'],
                'cohort_trainers_primary',
            );
            $table->foreign(
                ['organization_id', 'cohort_id'],
                'cohort_trainers_cohort_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('cohorts')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'trainer_membership_id'],
                'cohort_trainers_membership_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(['organization_id', 'trainer_membership_id']);
        });

        Schema::create('institution_invites', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->char('code_digest', 64);
            $table->string('membership_type', 40);
            $table->ulid('role_id')->nullable();
            $table->ulid('cohort_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestampTz('expires_at');
            $table->ulid('accepted_membership_id')->nullable();
            $table->foreignUlid('accepted_by_user_id')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('accepted_idempotency_key', 36)->nullable();
            $table->char('accepted_request_fingerprint', 64)->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'code_digest']);
            $table->unique(
                ['accepted_by_user_id', 'accepted_idempotency_key'],
                'institution_invites_acceptance_idempotency_unique',
            );
            $table->foreign(
                ['organization_id', 'role_id'],
                'institution_invites_role_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('roles')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'cohort_id'],
                'institution_invites_cohort_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('cohorts')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'organization_id',
                    'accepted_by_user_id',
                    'accepted_membership_id',
                ],
                'institution_invites_acceptance_identity_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(['organization_id', 'status', 'expires_at']);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('learner_membership_id');
            $table->ulid('cohort_id')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampTz('enrolled_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->foreign(
                ['organization_id', 'learner_membership_id'],
                'enrollments_membership_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'cohort_id'],
                'enrollments_cohort_tenant_fk',
            )->references(['organization_id', 'id'])
                ->on('cohorts')
                ->restrictOnDelete();
            $table->index(['organization_id', 'learner_membership_id', 'status']);
            $table->index(['organization_id', 'cohort_id', 'status']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE institution_invites ADD CONSTRAINT institution_invites_type_check CHECK (membership_type IN ('learner', 'trainer', 'organization_admin'))",
            );
            DB::statement(
                "ALTER TABLE institution_invites ADD CONSTRAINT institution_invites_status_check CHECK (status IN ('pending', 'accepted', 'revoked', 'expired'))",
            );
            DB::statement(
                'ALTER TABLE institution_invites ADD CONSTRAINT institution_invites_acceptance_check CHECK ((status = \'accepted\' AND accepted_membership_id IS NOT NULL AND accepted_by_user_id IS NOT NULL AND accepted_at IS NOT NULL) OR (status <> \'accepted\' AND accepted_membership_id IS NULL AND accepted_by_user_id IS NULL AND accepted_idempotency_key IS NULL AND accepted_request_fingerprint IS NULL AND accepted_at IS NULL))',
            );
            DB::statement(
                'ALTER TABLE institution_invites ADD CONSTRAINT institution_invites_idempotency_pair_check CHECK ((accepted_idempotency_key IS NULL AND accepted_request_fingerprint IS NULL) OR (accepted_idempotency_key IS NOT NULL AND accepted_request_fingerprint IS NOT NULL))',
            );
            DB::statement(
                "ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('active', 'completed', 'withdrawn'))",
            );
            DB::statement(
                'ALTER TABLE enrollments ADD CONSTRAINT enrollments_ended_at_check CHECK ((status IN (\'completed\', \'withdrawn\') AND ended_at IS NOT NULL) OR (status = \'active\' AND ended_at IS NULL))',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('institution_invites');
        Schema::dropIfExists('cohort_trainers');
        Schema::dropIfExists('membership_roles');
        Schema::dropIfExists('role_permissions');
    }
};
