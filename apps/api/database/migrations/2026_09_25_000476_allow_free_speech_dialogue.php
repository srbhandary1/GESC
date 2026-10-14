<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE conversation_dialogue_states DROP CONSTRAINT dialogue_state_values');
            DB::statement("ALTER TABLE conversation_dialogue_states ADD CONSTRAINT dialogue_state_values CHECK (flow IN ('roleplay_dialogue','question_help','free_speech') AND state IN ('awaiting_reply','retry_required','awaiting_question','awaiting_explanation_permission','complete','closed') AND revision >= 0 AND opportunity >= 1)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Fail rather than destroy free-speech records when rolling back.
            DB::statement('ALTER TABLE conversation_dialogue_states DROP CONSTRAINT dialogue_state_values');
            DB::statement("ALTER TABLE conversation_dialogue_states ADD CONSTRAINT dialogue_state_values CHECK (flow IN ('roleplay_dialogue','question_help') AND state IN ('awaiting_reply','retry_required','awaiting_question','awaiting_explanation_permission','complete','closed') AND revision >= 0 AND opportunity >= 1)");
        }
    }
};
