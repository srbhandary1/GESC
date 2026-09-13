<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $body = $this->tutorBody();
        DB::table('prompt_versions')
            ->where('prompt_key', 'friend.turn.v1')
            ->where('coaching_mode', 'tutor')
            ->where('status', 'active')
            ->update([
                'body' => $body,
                'body_sha256' => hash('sha256', $body),
            ]);
    }

    public function down(): void
    {
        $identity = "You are Swathi, the on-screen English teacher for Global Spoken English.\n"
            ."Never use any other personal name. Never introduce yourself as Maya or anyone else.\n"
            .'You are an AI practice partner, not a human.';
        $body = $identity."\nReturn one short English conversational reply as Swathi.\nProvide one explicit priority correction with a Kannada explanation and whether retry is recommended.\nNever invent scores or change learning records.";
        DB::table('prompt_versions')
            ->where('prompt_key', 'friend.turn.v1')
            ->where('coaching_mode', 'tutor')
            ->where('status', 'active')
            ->update([
                'body' => $body,
                'body_sha256' => hash('sha256', $body),
            ]);
    }

    private function tutorBody(): string
    {
        $identity = "You are Swathi, the on-screen English teacher for Global Spoken English.\n"
            ."Never use any other personal name. Never introduce yourself as Maya or anyone else.\n"
            .'You are an AI practice partner, not a human.';

        return $identity
            ."\nWhen lesson_drill.target_patterns are present, this is a grammar drill, not free conversation."
            .' Stay only on those patterns. Elicit one pattern at a time with a short model sentence, then ask the learner to say it.'
            .' If they wander off-topic, bring them back to the next unused pattern.'
            .' Correct only errors against those patterns. Ignore any other scenario topic such as past simple.'
            ."\nOtherwise return one short English conversational reply as Swathi."
            ."\nProvide one explicit priority correction with an explanation in the session ui_locale and whether retry is recommended."
            ."\nNever invent scores or change learning records.";
    }
};
