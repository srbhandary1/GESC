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
        Schema::create('mobile_token_families', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->index()
                ->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')
                ->constrained('oauth_clients')->cascadeOnDelete();
            $table->ulid('selected_organization_id')->nullable();
            $table->ulid('selected_membership_id')->nullable();
            $table->char('installation_digest', 64);
            $table->timestampTz('last_used_at');
            $table->timestampTz('absolute_expires_at');
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->string('revocation_reason', 64)->nullable();
            $table->timestampsTz();

            $table->index(
                ['user_id', 'revoked_at', 'absolute_expires_at'],
                'mobile_families_user_active_idx',
            );
            $table->index(
                ['client_id', 'installation_digest', 'revoked_at'],
                'mobile_families_installation_idx',
            );
            $table->foreign(
                [
                    'selected_organization_id',
                    'user_id',
                    'selected_membership_id',
                ],
                'mobile_families_selected_membership_identity_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('mobile_refresh_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('family_id')
                ->constrained('mobile_token_families')->cascadeOnDelete();
            $table->char('token_digest', 64)->unique();
            $table->char('passport_refresh_token_id', 80)->unique();
            $table->char('access_token_id', 80)->index();
            $table->timestampTz('expires_at');
            $table->timestampTz('rotated_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(
                ['family_id', 'rotated_at'],
                'mobile_refresh_tokens_family_active_idx',
            );
            $table->foreign('passport_refresh_token_id')
                ->references('id')->on('oauth_refresh_tokens')
                ->cascadeOnDelete();
            $table->foreign('access_token_id')
                ->references('id')->on('oauth_access_tokens')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE mobile_token_families ADD CONSTRAINT mobile_families_selected_membership_pair_check CHECK ((selected_organization_id IS NULL AND selected_membership_id IS NULL) OR (selected_organization_id IS NOT NULL AND selected_membership_id IS NOT NULL))',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_refresh_tokens');
        Schema::dropIfExists('mobile_token_families');
    }
};
