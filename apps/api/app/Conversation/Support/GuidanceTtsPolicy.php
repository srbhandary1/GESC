<?php

declare(strict_types=1);

namespace App\Conversation\Support;

use InvalidArgumentException;

/**
 * Confidence Conversation guidance TTS policy (ADR-0011).
 *
 * English model lines always use en-IN. L1 guidance voices are allowlisted by
 * UI locale. Text remains the primary surface; playback is optional.
 */
final class GuidanceTtsPolicy
{
    public const PURPOSE_MODEL_EN = 'model_en';

    public const PURPOSE_GUIDANCE_L1 = 'guidance_l1';

    /**
     * @var array<string, string>
     */
    private const VOICES = [
        'en-IN' => 'en-IN-NeerjaNeural',
        'hi-IN' => 'hi-IN-SwaraNeural',
        'kn-IN' => 'kn-IN-SapnaNeural',
        'ml-IN' => 'ml-IN-SobhanaNeural',
        'ta-IN' => 'ta-IN-PallaviNeural',
        'te-IN' => 'te-IN-ShrutiNeural',
    ];

    /**
     * @return list<string>
     */
    public static function purposes(): array
    {
        return [self::PURPOSE_MODEL_EN, self::PURPOSE_GUIDANCE_L1];
    }

    /**
     * @return array<string, string>
     */
    public static function voiceMap(): array
    {
        return self::VOICES;
    }

    /**
     * @return list<string>
     */
    public static function allowlistedVoices(): array
    {
        return array_values(array_unique(array_values(self::VOICES)));
    }

    public static function voiceFor(string $purpose, string $uiLocale): string
    {
        if (! in_array($purpose, self::purposes(), true)) {
            throw new InvalidArgumentException('Unsupported TTS purpose.');
        }

        if ($purpose === self::PURPOSE_MODEL_EN) {
            return self::VOICES['en-IN'];
        }

        if (! ConversationUiLocales::isSupported($uiLocale)) {
            throw new InvalidArgumentException('Unsupported UI locale for guidance TTS.');
        }

        $voice = self::VOICES[$uiLocale] ?? null;
        if (! is_string($voice)) {
            throw new InvalidArgumentException('No guidance voice mapped for locale.');
        }

        return $voice;
    }

    public static function isAllowlistedVoice(string $voice): bool
    {
        return in_array($voice, self::allowlistedVoices(), true);
    }

    /**
     * Confidence Conversation spoken lines must not depend on human recordings.
     */
    public static function allowsHumanLessonRecording(): bool
    {
        return false;
    }

    /**
     * Text is always sufficient; TTS failure must not block the session.
     */
    public static function textFirstFallbackRequired(): bool
    {
        return true;
    }
}
