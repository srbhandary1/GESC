<?php

declare(strict_types=1);

namespace App\Conversation\Support;

final class GuidanceTtsSsml
{
    public static function document(
        string $locale,
        string $voice,
        string $text,
    ): string {
        return sprintf(
            '<speak version="1.0" xml:lang="%s"><voice name="%s"><prosody rate="0.92">%s</prosody></voice></speak>',
            htmlspecialchars($locale, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            htmlspecialchars($voice, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8'),
        );
    }
}
