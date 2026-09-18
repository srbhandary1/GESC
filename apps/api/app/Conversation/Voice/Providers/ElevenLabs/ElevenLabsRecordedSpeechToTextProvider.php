<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\ElevenLabs;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ElevenLabs\ElevenLabsSpeechConfiguration;
use App\Conversation\Providers\ElevenLabs\ElevenLabsSpeechHttpBoundary;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use App\Conversation\Voice\VoiceOperationRejected;
use Illuminate\Http\Client\ConnectionException;
use JsonException;
use Throwable;

final readonly class ElevenLabsRecordedSpeechToTextProvider implements RecordedSpeechToTextProvider
{
    private const string CANDIDATE_KEY = 'elevenlabs-stt';

    public function __construct(
        private ElevenLabsSpeechConfiguration $configuration,
        private ElevenLabsSpeechHttpBoundary $http,
        private PcmWavInspector $wavInspector,
    ) {}

    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        $this->configuration->assertAuthorized(self::CANDIDATE_KEY, 'stt');
        if ($request->mimeType !== 'audio/wav' || $request->language !== 'en-IN') {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_policy_failure');
        }
        try {
            $metadata = $this->wavInspector->inspect($request->audioBytes);
        } catch (VoiceOperationRejected) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_input_invalid');
        }

        $maximumBytes = $this->configuration->integer(
            self::CANDIDATE_KEY,
            'maximum_json_response_bytes',
        );
        $started = hrtime(true);
        $url = $this->configuration->baseUrl(self::CANDIDATE_KEY)
            .$this->configuration->string(self::CANDIDATE_KEY, 'route')
            .'?enable_logging=false';
        try {
            $response = $this->http
                ->request(self::CANDIDATE_KEY, 'stt', $maximumBytes)
                ->withHeaders(['User-Agent' => 'global-spoken-english-elevenlabs-stt'])
                ->acceptJson()
                ->attach(
                    'file',
                    $request->audioBytes,
                    'audio.wav',
                    ['Content-Type' => 'audio/wav'],
                )
                ->post($url, [
                    'model_id' => $this->configuration->string(
                        self::CANDIDATE_KEY,
                        'api_model',
                    ),
                    'language_code' => 'en',
                    'tag_audio_events' => 'false',
                    'diarize' => 'false',
                    'timestamps_granularity' => 'word',
                    'file_format' => 'other',
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
        if (! str_starts_with(strtolower((string) $response->header('Content-Type')), 'application/json')) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_malformed_response', $latency);
        }
        $body = $this->http->boundedBody(self::CANDIDATE_KEY, $response, $maximumBytes);
        try {
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_malformed_response', $latency);
        }
        if (! is_array($payload)) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_malformed_response', $latency);
        }
        $transcript = trim((string) ($payload['text'] ?? ''));
        if ($transcript === '' || preg_match('//u', $transcript) !== 1 || mb_strlen($transcript) > 500) {
            throw $this->http->failure(self::CANDIDATE_KEY, 'provider_malformed_response', $latency);
        }

        return new RecordedSpeechToTextResult(
            transcript: $transcript,
            confidenceBps: $this->confidenceBps($payload['words'] ?? null),
            usage: new ProviderUsage(
                providerKey: self::CANDIDATE_KEY,
                modelVersion: $this->configuration->string(self::CANDIDATE_KEY, 'api_model'),
                latencyMilliseconds: $latency,
                inputUnits: $metadata->durationMs,
                outputUnits: mb_strlen($transcript),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'audio_milliseconds',
                outputUnitKind: 'unicode_characters',
                billableUnitKind: 'audio_milliseconds',
                billableUnitQuantity: $metadata->durationMs,
                locale: 'en-IN',
                region: 'india',
            ),
            responseMetadataSha256: $this->metadataHash($response->headers()),
        );
    }

    private function confidenceBps(mixed $words): int
    {
        if (! is_array($words)) {
            return 0;
        }
        $probabilities = [];
        foreach ($words as $word) {
            if (! is_array($word) || ($word['type'] ?? null) !== 'word') {
                continue;
            }
            $logProbability = $word['logprob'] ?? null;
            if (is_numeric($logProbability) && is_finite((float) $logProbability)) {
                $probabilities[] = max(0.0, min(1.0, exp((float) $logProbability)));
            }
        }
        if ($probabilities === []) {
            return 0;
        }

        return (int) round((array_sum($probabilities) / count($probabilities)) * 10_000);
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
