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
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignUlid('curriculum_owner_organization_id')
                ->nullable()
                ->after('cohort_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('curriculum_version_id')
                ->nullable()
                ->after('curriculum_owner_organization_id');
            $table->ulid('curriculum_publication_id')
                ->nullable()
                ->after('curriculum_version_id');
            $table->ulid('curriculum_share_grant_id')
                ->nullable()
                ->after('curriculum_publication_id');
            $table->timestampTz('activated_at')
                ->nullable()
                ->after('enrolled_at');

            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'enrollments_publication_pin_fk',
            )->references([
                'organization_id',
                'curriculum_version_id',
                'id',
            ])->on('curriculum_publications')
                ->restrictOnDelete();
            $table->foreign(
                [
                    'curriculum_owner_organization_id',
                    'curriculum_share_grant_id',
                    'organization_id',
                    'curriculum_version_id',
                    'curriculum_publication_id',
                ],
                'enrollments_shared_publication_grant_fk',
            )->references([
                'organization_id',
                'id',
                'recipient_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
            ])->on('curriculum_share_grants')
                ->restrictOnDelete();
            $table->index(
                [
                    'organization_id',
                    'curriculum_publication_id',
                    'status',
                ],
                'enrollments_publication_status_index',
            );
        });

        if (
            DB::table('enrollments')
                ->where('status', 'completed')
                ->whereNull('curriculum_publication_id')
                ->exists()
        ) {
            throw new RuntimeException(
                'Cannot migrate an unpinned completed enrollment; explicit classification is required.',
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_status_check',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_ended_at_check',
            );
            DB::statement(
                "ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('pending_curriculum', 'active', 'completed', 'withdrawn'))",
            );
            DB::statement(
                "ALTER TABLE enrollments ADD CONSTRAINT enrollments_ended_at_check CHECK ((status IN ('completed', 'withdrawn') AND ended_at IS NOT NULL) OR (status IN ('pending_curriculum', 'active') AND ended_at IS NULL))",
            );
        }

        DB::table('enrollments')
            ->where('status', 'active')
            ->whereNull('curriculum_publication_id')
            ->update([
                'status' => 'pending_curriculum',
                'updated_at' => now(),
            ]);

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE enrollments ADD CONSTRAINT enrollments_pin_completeness_check CHECK ((curriculum_owner_organization_id IS NULL AND curriculum_version_id IS NULL AND curriculum_publication_id IS NULL AND curriculum_share_grant_id IS NULL) OR (curriculum_owner_organization_id IS NOT NULL AND curriculum_version_id IS NOT NULL AND curriculum_publication_id IS NOT NULL))',
        );
        DB::statement(
            'ALTER TABLE enrollments ADD CONSTRAINT enrollments_pin_activation_check CHECK ((curriculum_publication_id IS NULL AND activated_at IS NULL) OR (curriculum_publication_id IS NOT NULL AND activated_at IS NOT NULL))',
        );
        DB::statement(
            "ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_pin_check CHECK ((status = 'pending_curriculum' AND curriculum_publication_id IS NULL) OR (status IN ('active', 'completed') AND curriculum_publication_id IS NOT NULL) OR status = 'withdrawn')",
        );
        DB::statement(
            'ALTER TABLE enrollments ADD CONSTRAINT enrollments_share_pin_check CHECK (curriculum_publication_id IS NULL OR (curriculum_owner_organization_id = organization_id AND curriculum_share_grant_id IS NULL) OR (curriculum_owner_organization_id <> organization_id AND curriculum_share_grant_id IS NOT NULL))',
        );

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
        DB::statement(
            'CREATE TRIGGER enrollments_pin_immutable BEFORE UPDATE ON enrollments FOR EACH ROW EXECUTE FUNCTION enrollment_enforce_pin_immutability()',
        );
    }

    public function down(): void
    {
        if (DB::table('enrollments')->exists()) {
            throw new RuntimeException(
                'Cannot roll back PR-005 enrollment pins while enrollment records exist. Preserve authoritative pins and deploy a reviewed forward-fix migration.',
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'DROP TRIGGER IF EXISTS enrollments_pin_immutable ON enrollments',
            );
            DB::statement(
                'DROP FUNCTION IF EXISTS enrollment_enforce_pin_immutability()',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_share_pin_check',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_status_pin_check',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_pin_activation_check',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_pin_completeness_check',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_ended_at_check',
            );
            DB::statement(
                'ALTER TABLE enrollments DROP CONSTRAINT enrollments_status_check',
            );
        }

        DB::table('enrollments')
            ->where('status', 'pending_curriculum')
            ->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('active', 'completed', 'withdrawn'))",
            );
            DB::statement(
                "ALTER TABLE enrollments ADD CONSTRAINT enrollments_ended_at_check CHECK ((status IN ('completed', 'withdrawn') AND ended_at IS NOT NULL) OR (status = 'active' AND ended_at IS NULL))",
            );
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropForeign('enrollments_shared_publication_grant_fk');
            $table->dropForeign('enrollments_publication_pin_fk');
            $table->dropForeign(
                'enrollments_curriculum_owner_organization_id_foreign',
            );
            $table->dropIndex('enrollments_publication_status_index');
            $table->dropColumn([
                'curriculum_owner_organization_id',
                'curriculum_version_id',
                'curriculum_publication_id',
                'curriculum_share_grant_id',
                'activated_at',
            ]);
        });
    }
};
