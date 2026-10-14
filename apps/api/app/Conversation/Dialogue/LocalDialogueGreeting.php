<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

/** Exact conversational greetings only; substantive questions still reach the model. */
final class LocalDialogueGreeting
{
    /** @param array<string, mixed> $context */
    public static function reply(string $transcript, array $context): ?string
    {
        if (($context['flow'] ?? null) !== 'question_help'
            || ($context['action'] ?? null) !== 'question'
            || ($context['requested_reply_locale'] ?? 'en-IN') !== 'en-IN') {
            return null;
        }
        if (preg_match('/^\s*(hello|hi|good\s+(?:morning|afternoon|evening))[\s,.!]*(?:swathi[\s.!]*)?$/iu', $transcript, $match) !== 1) {
            return null;
        }
        $greeting = ucfirst(mb_strtolower(preg_replace('/\s+/u', ' ', $match[1]) ?? 'Hello'));
        if (is_array($context['lesson'] ?? null)) {
            return $greeting.'! What would you like me to explain about this lesson?';
        }

        return $greeting.'! What would you like to practise?';
    }
}
