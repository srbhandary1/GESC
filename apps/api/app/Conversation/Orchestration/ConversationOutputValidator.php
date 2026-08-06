<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

use App\Conversation\Providers\ConversationModelResult;

final class ConversationOutputValidator
{
    public function validate(ConversationModelResult $result): bool
    {
        if (
            ! $this->safeText($result->understoodIntent, 100)
            || ! $this->safeText($result->assistantReply, 1000)
            || ! in_array($result->safetyState, ['ok', 'fallback'], true)
        ) {
            return false;
        }

        $correction = $result->priorityCorrection;
        if ($correction === null) {
            return true;
        }

        return $this->safeToken($correction->errorCategory, 64)
            && $this->safeText($correction->correctionEn, 500)
            && $this->safeText($correction->explanationKn, 500);
    }

    private function safeText(string $value, int $maximum): bool
    {
        $trimmed = trim($value);

        return $trimmed !== ''
            && preg_match('//u', $trimmed) === 1
            && mb_strlen($trimmed) <= $maximum
            && ! str_contains($trimmed, '<')
            && ! str_contains($trimmed, '>');
    }

    private function safeToken(string $value, int $maximum): bool
    {
        return preg_match(
            '/\A[a-z0-9][a-z0-9._-]{0,'.($maximum - 1).'}\z/',
            $value,
        ) === 1;
    }
}
