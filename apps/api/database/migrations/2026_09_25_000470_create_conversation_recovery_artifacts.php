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
        Schema::create('conversation_recovery_artifacts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->ulid('learner_membership_id');
            $table->ulid('conversation_session_id');
            $table->ulid('conversation_turn_id')->nullable();
            $table->string('kind', 16);
            $table->string('storage_path', 100)->unique();
            $table->char('ciphertext_sha256', 64);
            $table->string('status', 24);
            $table->unsignedInteger('deletion_attempts')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('deleted_at')->nullable();
            $table->unique(['organization_id', 'id']);
            $table->index(['status', 'expires_at'], 'conversation_recovery_retention_idx');
            $table->foreign(
                ['organization_id', 'learner_membership_id'],
                'conversation_recovery_membership_fk',
            )->references(['organization_id', 'id'])->on('memberships')->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'conversation_session_id', 'learner_membership_id'],
                'conversation_recovery_session_fk',
            )->references(['organization_id', 'id', 'learner_membership_id'])
                ->on('conversation_sessions')->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'conversation_turn_id', 'conversation_session_id', 'learner_membership_id'],
                'conversation_recovery_turn_fk',
            )->references(['organization_id', 'id', 'conversation_session_id', 'learner_membership_id'])
                ->on('conversation_turns')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversation_recovery_artifacts ADD CONSTRAINT conversation_recovery_values_check CHECK (kind IN ('start', 'turn') AND status IN ('writing', 'active', 'deletion_pending', 'deleted') AND ciphertext_sha256 ~ '^[0-9a-f]{64}$' AND expires_at > created_at AND expires_at <= created_at + interval '15 minutes' AND (status = 'deleted') = (deleted_at IS NOT NULL) AND (kind = 'start') = (conversation_turn_id IS NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_recovery_artifacts');
    }
};
