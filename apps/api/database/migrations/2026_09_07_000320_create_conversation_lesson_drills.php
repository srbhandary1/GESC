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
        Schema::create('conversation_lesson_drills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignUlid('conversation_session_id')
                ->constrained('conversation_sessions')
                ->restrictOnDelete();
            $table->foreignUlid('learner_membership_id')
                ->constrained('memberships')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('level_number');
            $table->string('part_id', 32);
            $table->string('concept_key', 64);
            $table->string('communication_function', 80)->nullable();
            $table->string('talk_scenario', 400)->nullable();
            $table->json('target_patterns');
            $table->json('elicitation_goals');
            $table->timestampTz('created_at');
            $table->unique('conversation_session_id');
            $table->index(['organization_id', 'learner_membership_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE conversation_lesson_drills ADD CONSTRAINT conversation_lesson_drills_values_check CHECK (level_number BETWEEN 1 AND 17 AND part_id IN ('introduction', 'practice') AND concept_key ~ '^[a-z0-9][a-z0-9_]{0,62}$')",
            );
            DB::unprepared(<<<'SQL'
CREATE TRIGGER conversation_lesson_drills_immutable
BEFORE UPDATE OR DELETE ON conversation_lesson_drills
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS conversation_lesson_drills_immutable ON conversation_lesson_drills');
        }

        Schema::dropIfExists('conversation_lesson_drills');
    }
};
