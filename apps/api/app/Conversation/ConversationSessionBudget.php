<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use Illuminate\Support\Facades\DB;

/**
 * LPL-06d: per-session learner-turn and token ceilings before live dispatch.
 */
final readonly class ConversationSessionBudget
{
    public function assertCanAcceptLearnerTurn(
        string $organizationId,
        string $sessionId,
    ): void {
        $maxTurns = (int) config('conversation.session.max_learner_turns', 8);
        if ($maxTurns < 1) {
            return;
        }

        $acceptedTurns = (int) DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('conversation_session_id', $sessionId)
            ->whereIn('status', ['accepted', 'completed'])
            ->count();

        if ($acceptedTurns >= $maxTurns) {
            throw new ConversationOperationRejected('session_turn_limit');
        }

        $maxTokens = (int) config('conversation.session.max_tokens', 0);
        if ($maxTokens < 1) {
            return;
        }

        // Model tokens only. STT/TTS ledger rows track characters and audio
        // samples; counting them here would exhaust the session after one reply.
        $usage = DB::table('ai_cost_ledger')
            ->where('organization_id', $organizationId)
            ->where('conversation_session_id', $sessionId)
            ->where('provider_stage', 'model')
            ->selectRaw(
                'COALESCE(SUM(input_units + output_units), 0) as total_tokens',
            )
            ->value('total_tokens');

        if ((int) $usage >= $maxTokens) {
            throw new ConversationOperationRejected('session_token_limit');
        }
    }
}
