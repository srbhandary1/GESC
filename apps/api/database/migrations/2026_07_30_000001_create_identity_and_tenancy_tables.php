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
        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('system_key', 80)->nullable()->unique();
            $table->string('name', 160);
            $table->string('kind', 40);
            $table->string('status', 24)->default('active');
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->timestampsTz();

            $table->index(['status', 'id']);
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('display_name', 160)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['status', 'id']);
        });

        Schema::create('user_identities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('type', 40);
            $table->char('identifier_digest', 64);
            $table->string('display_hint', 40)->nullable();
            $table->string('status', 24)->default('verified');
            $table->timestampTz('verified_at');
            $table->timestampsTz();

            $table->unique(['type', 'identifier_digest']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key', 120)->unique();
            $table->string('description', 240);
            $table->timestampsTz();
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('membership_type', 40);
            $table->string('status', 24)->default('active');
            $table->timestampTz('joined_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'user_id', 'id'],
                'memberships_organization_user_id_unique',
            );
            $table->unique(['organization_id', 'user_id']);
            $table->index(['organization_id', 'status', 'id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('key', 80);
            $table->string('name', 120);
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'name']);
        });

        Schema::create('cohorts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('key', 80);
            $table->string('name', 160);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'status', 'id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE organizations ADD CONSTRAINT organizations_kind_check CHECK (kind IN ('platform_direct', 'institution'))",
            );
            DB::statement(
                "ALTER TABLE organizations ADD CONSTRAINT organizations_status_check CHECK (status IN ('active', 'suspended', 'closed'))",
            );
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'suspended', 'closed'))",
            );
            DB::statement(
                "ALTER TABLE user_identities ADD CONSTRAINT user_identities_status_check CHECK (status IN ('verified', 'disabled'))",
            );
            DB::statement(
                "ALTER TABLE memberships ADD CONSTRAINT memberships_type_check CHECK (membership_type IN ('learner', 'trainer', 'organization_admin'))",
            );
            DB::statement(
                "ALTER TABLE memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active', 'suspended', 'ended'))",
            );
            DB::statement(
                'ALTER TABLE memberships ADD CONSTRAINT memberships_ended_at_check CHECK ((status = \'ended\' AND ended_at IS NOT NULL) OR (status <> \'ended\' AND ended_at IS NULL))',
            );
            DB::statement(
                "ALTER TABLE cohorts ADD CONSTRAINT cohorts_status_check CHECK (status IN ('active', 'archived'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cohorts');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('user_identities');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
    }
};
