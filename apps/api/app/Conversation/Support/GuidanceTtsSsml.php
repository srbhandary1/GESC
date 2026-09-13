<?php

declare(strict_types=1);

namespace App\Conversation\Support;

final class GuidanceTtsSsml
{
    public static function document(
        string $locale,
        string $voice,
        string $text,
        ?string $embeddedLatinVoice = null,
    ): string {
        $voiceSegments = self::voiceSegments(
            $voice,
            $text,
            $embeddedLatinVoice,
        );

        return sprintf(
            '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="%s">%s</speak>',
            htmlspecialchars($locale, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            implode('', array_map(
                static fn (array $segment): string => sprintf(
                    '<voice name="%s"><prosody rate="0.92">%s</prosody></voice>',
                    htmlspecialchars(
                        $segment['voice'],
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8',
                    ),
                    htmlspecialchars(
                        $segment['text'],
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8',
                    ),
                ),
                $voiceSegments,
            )),
        );
    }

    /**
     * Standard Azure locale voices are not multilingual. Keep non-Latin text
     * on the selected L1 voice and route embedded Latin-script runs through
     * the separately allowlisted English voice.
     *
     * @return list<array{voice: string, text: string}>
     */
    private static function voiceSegments(
        string $primaryVoice,
        string $text,
        ?string $embeddedLatinVoice,
    ): array {
        if ($embeddedLatinVoice === null || $embeddedLatinVoice === $primaryVoice) {
            return [['voice' => $primaryVoice, 'text' => $text]];
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false || $characters === []) {
            return [['voice' => $primaryVoice, 'text' => $text]];
        }

        $segments = [];
        $currentVoice = null;
        $currentText = '';
        $pendingNeutralText = '';

        foreach ($characters as $character) {
            $targetVoice = match (true) {
                preg_match('/\p{Latin}/u', $character) === 1 => $embeddedLatinVoice,
                preg_match('/\p{L}/u', $character) === 1 => $primaryVoice,
                default => null,
            };

            if ($targetVoice === null) {
                $pendingNeutralText .= $character;

                continue;
            }

            if ($currentVoice === null) {
                $currentVoice = $targetVoice;
                $currentText = $pendingNeutralText.$character;
                $pendingNeutralText = '';

                continue;
            }

            if ($targetVoice === $currentVoice) {
                $currentText .= $pendingNeutralText.$character;
                $pendingNeutralText = '';

                continue;
            }

            $segments[] = [
                'voice' => $currentVoice,
                'text' => $currentText.$pendingNeutralText,
            ];
            $currentVoice = $targetVoice;
            $currentText = $character;
            $pendingNeutralText = '';
        }

        if ($currentVoice === null) {
            return [['voice' => $primaryVoice, 'text' => $text]];
        }

        $segments[] = [
            'voice' => $currentVoice,
            'text' => $currentText.$pendingNeutralText,
        ];

        return $segments;
    }
}
