<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use RuntimeException;

/** Never chain storage/cryptography exceptions: their messages may carry data. */
final class ConversationRecoveryUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('conversation_recovery_unavailable');
    }
}
