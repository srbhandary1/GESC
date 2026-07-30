<?php

declare(strict_types=1);

namespace App\Conversation;

use LogicException;

final class ConversationTurnStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'accepted' => ['transcribing', 'failed'],
        'transcribing' => ['generating_reply', 'failed'],
        'generating_reply' => ['synthesizing', 'failed'],
        'synthesizing' => ['completed', 'failed'],
        'completed' => [],
        'failed' => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new LogicException(sprintf(
                'Conversation transition from %s to %s is not allowed.',
                $from,
                $to,
            ));
        }
    }
}
