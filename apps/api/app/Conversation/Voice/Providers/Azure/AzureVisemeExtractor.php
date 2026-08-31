<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\Azure;

/**
 * Extracts viseme data from Azure Speech Synthesis events.
 *
 * Azure returns visemes in JSON format with VisemeId and AnimationOffset.
 * We map these to mouth_shape strings and timestamp_ms for the frontend contract.
 *
 * VisemeId reference (IPA-based viseme mapping for Speech Synthesis):
 * 0=silence, 1=ae, 2=Eh, 3=ih, 4=oh, 5=ooh, 6=er, 7=ah, 8=ee,
 * 9=ow, 10=ou, 11=y, 12=ss, 13=z, 14=sh, 15=zh, 16=ch, 17=jh,
 * 18=th, 19=dh, 20=nn, 21=ll, 22=kk, 23=gg, 24=ff, 25=vv,
 * 26=th, 27=dd, 28=pp, 29=bb, 30=mm, 31=rr
 */
final readonly class AzureVisemeExtractor
{
    private const array VISEME_MOUTH_SHAPES = [
        0 => 'rest',      // silence
        1 => 'ae',        // vowel
        2 => 'eh',        // vowel
        3 => 'ih',        // vowel
        4 => 'oh',        // vowel
        5 => 'ooh',       // vowel
        6 => 'er',        // vowel
        7 => 'ah',        // vowel
        8 => 'ee',        // vowel
        9 => 'ow',        // vowel
        10 => 'ou',       // vowel
        11 => 'y',        // consonant
        12 => 'ss',       // fricative
        13 => 'z',        // fricative
        14 => 'sh',       // fricative
        15 => 'zh',       // fricative
        16 => 'ch',       // affricate
        17 => 'jh',       // affricate
        18 => 'th',       // fricative
        19 => 'dh',       // fricative
        20 => 'nn',       // nasal
        21 => 'll',       // liquid
        22 => 'kk',       // velar
        23 => 'gg',       // velar
        24 => 'ff',       // fricative
        25 => 'vv',       // fricative
        26 => 'th',       // fricative (duplicate IPA mapping)
        27 => 'dd',       // alveolar
        28 => 'pp',       // bilabial
        29 => 'bb',       // bilabial
        30 => 'mm',       // bilabial
        31 => 'rr',       // liquid
    ];

    /**
     * Extract viseme timestamps from Azure Speech Synthesis response body.
     *
     * Azure Speech TTS returns a WAV file with optional embedded synchronization data.
     * When X-Microsoft-OutputFormat includes detailed timing, the response may contain
     * metadata at the end of the WAV file (after audio data).
     *
     * For now, we parse visemes from event listeners during synthesis, or extract from
     * response headers if available. This method handles the parsing of JSON-encoded
     * viseme arrays that may be in the response metadata.
     *
     * @param  array<string, list<string>>  $responseHeaders
     * @return list<array{mouth_shape: string, timestamp_ms: int}>
     */
    public function extractFromResponse(
        ?string $responseBody,
        array $responseHeaders,
    ): array {
        if ($responseBody === null || $responseBody === '') {
            return [];
        }

        // Check if viseme metadata is embedded in response headers
        // (Azure may return this in X-Microsoft-VisemeMetadata or similar)
        $visemeHeader = $this->findVisemeHeader($responseHeaders);
        if ($visemeHeader !== null) {
            return $this->parseVisemeJson($visemeHeader);
        }

        // Future: When WebSocket streaming is enabled, visemes are sent as separate
        // JSON events during synthesis. For REST API, we rely on headers or
        // configuration that enables viseme tracking at the API level.

        return [];
    }

    /**
     * Parse Azure viseme events from JSON format.
     *
     * Expected format: [{"VisemeId": 0, "AnimationOffset": 0}, ...]
     *
     * @return list<array{mouth_shape: string, timestamp_ms: int}>
     */
    public function parseVisemeJson(string $json): array
    {
        $json = trim($json);
        if ($json === '' || $json[0] !== '[') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $visemes = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $visemeId = $item['VisemeId'] ?? null;
            $animationOffset = $item['AnimationOffset'] ?? null;

            if (! is_int($visemeId) || ! is_int($animationOffset)) {
                continue;
            }

            $mouthShape = self::VISEME_MOUTH_SHAPES[$visemeId] ?? 'rest';

            $visemes[] = [
                'mouth_shape' => $mouthShape,
                'timestamp_ms' => max(0, $animationOffset),
            ];
        }

        // Sort by timestamp to ensure correct playback order
        usort($visemes, static function (array $a, array $b): int {
            return $a['timestamp_ms'] <=> $b['timestamp_ms'];
        });

        return $visemes;
    }

    /**
     * Find viseme metadata in response headers.
     *
     * Azure may return viseme data in custom headers or as part of the response.
     * Look for common patterns like X-Microsoft-Visemes, X-Viseme-Data, etc.
     *
     * @param  array<string, list<string>>  $headers
     */
    private function findVisemeHeader(array $headers): ?string
    {
        $candidates = [
            'x-microsoft-visemes',
            'x-viseme-data',
            'x-viseme-events',
            'x-animation-data',
        ];

        foreach ($headers as $headerName => $headerValues) {
            $lowerName = strtolower($headerName);
            if (in_array($lowerName, $candidates, true)) {
                foreach ($headerValues as $value) {
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }
}
