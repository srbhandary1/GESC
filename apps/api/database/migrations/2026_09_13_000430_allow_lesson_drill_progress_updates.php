<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION conversation_enforce_lesson_drill_progress()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.id IS DISTINCT FROM NEW.id
        OR OLD.organization_id IS DISTINCT FROM NEW.organization_id
        OR OLD.conversation_session_id IS DISTINCT FROM NEW.conversation_session_id
        OR OLD.learner_membership_id IS DISTINCT FROM NEW.learner_membership_id
        OR OLD.level_number IS DISTINCT FROM NEW.level_number
        OR OLD.part_id IS DISTINCT FROM NEW.part_id
        OR OLD.concept_key IS DISTINCT FROM NEW.concept_key
        OR OLD.communication_function IS DISTINCT FROM NEW.communication_function
        OR OLD.talk_scenario IS DISTINCT FROM NEW.talk_scenario
        OR OLD.target_patterns::text IS DISTINCT FROM NEW.target_patterns::text
        OR OLD.elicitation_goals::text IS DISTINCT FROM NEW.elicitation_goals::text
        OR OLD.fallback_turns::text IS DISTINCT FROM NEW.fallback_turns::text
        OR OLD.created_at IS DISTINCT FROM NEW.created_at
    THEN
        RAISE EXCEPTION 'Conversation lesson drill identity is immutable'
            USING ERRCODE = '55000';
    END IF;

    IF NEW.accepted_pattern_count < OLD.accepted_pattern_count THEN
        RAISE EXCEPTION 'Conversation lesson drill progress cannot regress'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS conversation_lesson_drills_immutable ON conversation_lesson_drills;

CREATE TRIGGER conversation_lesson_drills_progress
BEFORE UPDATE ON conversation_lesson_drills
FOR EACH ROW EXECUTE FUNCTION conversation_enforce_lesson_drill_progress();

CREATE TRIGGER conversation_lesson_drills_no_delete
BEFORE DELETE ON conversation_lesson_drills
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS conversation_lesson_drills_no_delete ON conversation_lesson_drills;
DROP TRIGGER IF EXISTS conversation_lesson_drills_progress ON conversation_lesson_drills;
DROP FUNCTION IF EXISTS conversation_enforce_lesson_drill_progress();

CREATE TRIGGER conversation_lesson_drills_immutable
BEFORE UPDATE OR DELETE ON conversation_lesson_drills
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();
SQL);
    }
};
