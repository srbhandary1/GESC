<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use RuntimeException;

/** An admitted turn has no committed replay: restart, never redispatch it. */
final class DialogueTurnOutcomeUnknown extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('conversation_turn_outcome_unknown');
    }
}
