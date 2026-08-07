<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Support\ConversationUiLocales;

final class ConversationOutputValidator
{
    /**
     * Case-insensitive shame / humiliation phrases (non-exhaustive).
     *
     * @var list<string>
     */
    private const SHAME_PHRASES = [
        'stupid',
        'bad english',
        'thick accent',
        'you will never',
        'worse than other',
        'dummy',
        'idiot',
    ];

    public function validate(ConversationModelResult $result): bool
    {
        if (
            ! $this->safeText($result->understoodIntent, 100)
            || ! $this->safeText($result->assistantReply, 1000)
            || ! in_array($result->safetyState, ['ok', 'fallback'], true)
            || $this->containsShame($result->assistantReply)
            || $this->containsShame($result->understoodIntent)
        ) {
            return false;
        }

        $correction = $result->priorityCorrection;
        if ($correction === null) {
            return true;
        }

        if ($correction->needed === false) {
            return true;
        }

        return $this->safeToken($correction->errorCategory, 64)
            && $this->safeText($correction->correctionEn, 500)
            && $this->safeText($correction->explanationL1, 500)
            && ConversationUiLocales::isSupported($correction->explanationLocale)
            && ! $this->containsShame($correction->correctionEn)
            && ! $this->containsShame($correction->explanationL1)
            && (
                $correction->encouragementL1 === null
                || $correction->encouragementL1 === ''
                || (
                    $this->safeText($correction->encouragementL1, 500)
                    && ! $this->containsShame($correction->encouragementL1)
                )
            )
            && (
                $correction->learnerSpan === null
                || $correction->learnerSpan === ''
                || $this->safeText($correction->learnerSpan, 200)
            );
    }

    private function containsShame(string $value): bool
    {
        $haystack = mb_strtolower($value);

        foreach (self::SHAME_PHRASES as $phrase) {
            if (str_contains($haystack, $phrase)) {
                return true;
            }
        }

        return false;
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
