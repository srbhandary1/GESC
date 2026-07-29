<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index()
                ->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('authentication_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->index()
                ->constrained('users')->cascadeOnDelete();
            $table->char('storage_session_digest', 64)->unique();
            $table->text('storage_session_ciphertext');
            $table->boolean('is_privileged')->default(false);
            $table->timestampTz('started_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('idle_expires_at');
            $table->timestampTz('absolute_expires_at');
            $table->timestampTz('mfa_verified_at')->nullable();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->string('revocation_reason', 64)->nullable();
            $table->timestampsTz();

            $table->index(
                ['user_id', 'revoked_at', 'absolute_expires_at'],
                'auth_sessions_user_active_idx',
            );
        });

        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('destination_digest', 64);
            $table->char('code_digest', 64);
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->index(
                ['destination_digest', 'consumed_at', 'expires_at'],
                'otp_challenges_destination_active_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('authentication_sessions');
        Schema::dropIfExists('sessions');
    }
};
