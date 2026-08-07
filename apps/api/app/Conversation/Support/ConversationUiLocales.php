<?php

declare(strict_types=1);

namespace App\Conversation\Support;

final class ConversationUiLocales
{
    /**
     * Phase 1 UI locales (ADR-0005). Explanation language follows these.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'kn-IN',
            'ml-IN',
            'ta-IN',
            'te-IN',
            'hi-IN',
            'en-IN',
        ];
    }

    public static function default(): string
    {
        return 'kn-IN';
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::all(), true);
    }
}
