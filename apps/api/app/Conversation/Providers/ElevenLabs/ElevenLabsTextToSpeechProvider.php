<?php

declare(strict_types=1);

namespace App\Conversation\Providers\ElevenLabs;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\VoiceOperationRejected;
use Illuminate\Http\Client\ConnectionException;
use JsonException;
use Throwable;

final readonly class ElevenLabsTextToSpeechProvider implements TextToSpeechProvider
{
    private const string CANDIDATE_KEY = 'elevenlabs-tts';

    public function __construct(
        private ElevenLabsSpeechConfiguration $configuration,
        private ElevenLabsSpeechHttpBoundary $http,
        private PcmWavInspector $wavInspector,
    ) {}

    public function synthesize(TextToSpeechRequest $request): TextToSpeechResult
    {
        $this->configuration->assertAuthorized(self::CANDIDATE_KEY, 'tts');
        $text = trim($request->text);
        if (
            $text === ''
            || preg_match('//u', $text) !== 1
            || mb_strlen($text) > $this->configuration->integer(
                self::CANDIDATE_KEY,
                'maximum_characters',
            )
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1
            || ! in_array($request->purpose, GuidanceTtsPolicy::purposes(), true)
            || ! GuidanceTtsPolicy::isAllowlistedVoice($request->voice)
        ) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_input_invalid');
        }

        $voiceId = $this->configuration->string(self::CANDIDATE_KEY, 'voice_id');
        if (preg_match('/\A[A-Za-z0-9_-]{8,64}\z/', $voiceId) !== 1) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_not_authorized');
        }

        $maximumBytes = $this->configuration->integer(
            self::CANDIDATE_KEY,
            'maximum_response_bytes',
        );
        $started = hrtime(true);
        $url = $this->configuration->baseUrl(self::CANDIDATE_KEY)
            .$this->configuration->string(self::CANDIDATE_KEY, 'route')
            .'/'.$voiceId.'?output_format='
            .rawurlencode($this->configuration->string(self::CANDIDATE_KEY, 'output_format'))
            .'&enable_logging=false';

        try {
            $response = $this->http
                ->request(self::CANDIDATE_KEY, 'tts', $maximumBytes)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'global-spoken-english-elevenlabs-tts',
                ])
                ->accept('audio/pcm')
                ->post($url, [
                    'text' => $text,
                    'model_id' => $this->configuration->string(
                        self::CANDIDATE_KEY,
                        'api_model',
                    ),
                    'language_code' => $this->languageCode($request->uiLocale),
                ]);
        } catch (ConversationProviderException $exception) {
            throw $exception;
        } catch (ConnectionException) {
            throw $this->http->failure(
                self::CANDIDATE_KEY,
                'provider_connection_failure',
                $this->elapsed($started),
                true,
            );
        } catch (Throwable) {
            throw $this->http->failure(
                self::CANDIDATE_KEY,
                'provider_transport_failure',
                $this->elapsed($started),
                true,
            );
        }

        $latency = $this->elapsed($started);
        if ($response->status() !== 200) {
            throw $this->http->fromStatus(self::CANDIDATE_KEY, $response->status(), $latency);
        }
        $contentType = strtolower((string) $response->header('Content-Type'));
        if (
            ! str_starts_with($contentType, 'audio/pcm')
            && ! str_starts_with($contentType, 'application/octet-stream')
        ) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_malformed_response', $latency);
        }

        $pcm = $this->http->boundedBody(
            self::CANDIDATE_KEY,
            $response,
            $maximumBytes,
        );
        if (strlen($pcm) % 2 !== 0) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_malformed_response', $latency);
        }
        $audio = $this->wav($pcm, 24_000);
        try {
            $this->wavInspector->inspect($audio);
        } catch (VoiceOperationRejected) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_malformed_response', $latency);
        }

        $characters = mb_strlen($text);

        return new TextToSpeechResult(
            playbackAvailable: true,
            renderingMode: 'elevenlabs-india-zero-retention-wav',
            providerUsage: new ProviderUsage(
                providerKey: self::CANDIDATE_KEY,
                modelVersion: $this->configuration->string(self::CANDIDATE_KEY, 'api_model'),
                latencyMilliseconds: $latency,
                inputUnits: $characters,
                outputUnits: strlen($audio),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'unicode_characters',
                outputUnitKind: 'audio_bytes',
                billableUnitKind: 'unicode_characters',
                billableUnitQuantity: $characters,
                voiceKey: $request->voice,
                locale: $request->uiLocale,
                region: 'india',
            ),
            audioBytes: $audio,
            mimeType: 'audio/wav',
            responseMetadataSha256: $this->metadataHash($response->headers()),
        );
    }

    private function languageCode(string $locale): string
    {
        $language = strtolower(substr(trim($locale), 0, 2));

        return preg_match('/\A[a-z]{2}\z/', $language) === 1 ? $language : 'en';
    }

    private function wav(string $pcm, int $sampleRate): string
    {
        $format = 'fmt '.pack(
            'VvvVVvv',
            16,
            1,
            1,
            $sampleRate,
            $sampleRate * 2,
            2,
            16,
        );
        $data = 'data'.pack('V', strlen($pcm)).$pcm;
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }

    /** @param array<string, list<string>> $headers */
    private function metadataHash(array $headers): string
    {
        $safe = [];
        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), ['request-id', 'x-request-id', 'date'], true)) {
                $safe[strtolower($name)] = array_map(
                    static fn (string $value): string => hash('sha256', $value),
                    $values,
                );
            }
        }
        ksort($safe);

        try {
            return hash('sha256', json_encode($safe, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return hash('sha256', 'metadata-unavailable');
        }
    }

    private function elapsed(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
