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
        Schema::create('conversation_dialogue_turn_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('learner_membership_id');
            $table->ulid('conversation_session_id');
            $table->foreignUlid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->char('request_fingerprint', 64);
            $table->unsignedInteger('expected_revision');
            $table->string('status', 16);
            $table->ulid('completed_turn_id')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'conversation_session_id', 'idempotency_key'], 'dialogue_reservation_command_unique');
            $table->unique(['organization_id', 'conversation_session_id', 'expected_revision'], 'dialogue_reservation_revision_unique');
            $table->foreign(['organization_id', 'conversation_session_id', 'learner_membership_id'], 'dialogue_reservation_session_fk')
                ->references(['organization_id', 'id', 'learner_membership_id'])->on('conversation_sessions')->restrictOnDelete();
            $table->foreign(['organization_id', 'completed_turn_id', 'conversation_session_id', 'learner_membership_id'], 'dialogue_reservation_turn_fk')
                ->references(['organization_id', 'id', 'conversation_session_id', 'learner_membership_id'])->on('conversation_turns')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversation_dialogue_turn_reservations ADD CONSTRAINT dialogue_reservation_values CHECK (status IN ('reserved','completed') AND request_fingerprint ~ '^[0-9a-f]{64}$' AND ((status = 'reserved' AND completed_turn_id IS NULL AND completed_at IS NULL) OR (status = 'completed' AND completed_turn_id IS NOT NULL AND completed_at IS NOT NULL)))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_dialogue_turn_reservations');
    }
};
