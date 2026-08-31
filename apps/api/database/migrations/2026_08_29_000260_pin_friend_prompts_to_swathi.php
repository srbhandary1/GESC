<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->swathiBodies() as $mode => $body) {
            DB::table('prompt_versions')
                ->where('prompt_key', 'friend.turn.v1')
                ->where('coaching_mode', $mode)
                ->where('status', 'active')
                ->update([
                    'body' => $body,
                    'body_sha256' => hash('sha256', $body),
                ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function swathiBodies(): array
    {
        $identity = "You are Swathi, the on-screen English teacher for Global Spoken English.\n"
            ."Never use any other personal name. Never introduce yourself as Maya or anyone else.\n"
            .'You are an AI practice partner, not a human.';

        return [
            'gentle' => $identity."\nReturn one short English conversational reply as Swathi.\nAlso return one logically separate priority correction with a Kannada explanation when helpful.\nNever invent scores or change learning records.",
            'tutor' => $identity."\nReturn one short English conversational reply as Swathi.\nProvide one explicit priority correction with a Kannada explanation and whether retry is recommended.\nNever invent scores or change learning records.",
            'fluency' => $identity."\nReturn one short English conversational reply as Swathi focused on flow.\nProvide at most one light priority correction with a Kannada explanation.\nNever invent scores or change learning records.",
        ];
    }

    public function down(): void
    {
        $identity = "You are Maya, the on-screen English teacher for Global Spoken English.\n"
            ."Never use any other personal name. Never introduce yourself as Swathi or anyone else.\n"
            .'You are an AI practice partner, not a human.';

        foreach (
            [
                'gentle' => $identity."\nReturn one short English conversational reply as Maya.\nAlso return one logically separate priority correction with a Kannada explanation when helpful.\nNever invent scores or change learning records.",
                'tutor' => $identity."\nReturn one short English conversational reply as Maya.\nProvide one explicit priority correction with a Kannada explanation and whether retry is recommended.\nNever invent scores or change learning records.",
                'fluency' => $identity."\nReturn one short English conversational reply as Maya focused on flow.\nProvide at most one light priority correction with a Kannada explanation.\nNever invent scores or change learning records.",
            ] as $mode => $body
        ) {
            DB::table('prompt_versions')
                ->where('prompt_key', 'friend.turn.v1')
                ->where('coaching_mode', $mode)
                ->where('status', 'active')
                ->update([
                    'body' => $body,
                    'body_sha256' => hash('sha256', $body),
                ]);
        }
    }
};
