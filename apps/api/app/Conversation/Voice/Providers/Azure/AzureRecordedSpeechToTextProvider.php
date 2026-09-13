<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\Azure;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use App\Conversation\Voice\VoiceOperationRejected;
use Illuminate\Http\Client\ConnectionException;
use JsonException;
use Throwable;

final readonly class AzureRecordedSpeechToTextProvider implements RecordedSpeechToTextProvider
{
    public function __construct(
        private AzureSpeechConfiguration $configuration,
        private AzureSpeechHttpBoundary $http,
        private PcmWavInspector $wavInspector,
    ) {}

    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        $this->configuration->assertLiveAuthorized();

        if (
            $request->mimeType !== 'audio/wav'
            || $request->language
                !== $this->configuration->string('speech.azure.stt.locale')
        ) {
            throw $this->http->failure('provider_policy_failure');
        }

        try {
            $metadata = $this->wavInspector->inspect($request->audioBytes);
        } catch (VoiceOperationRejected) {
            throw $this->http->failure('provider_input_invalid');
        }

        $definition = json_encode([
            'locales' => [$request->language],
            'profanityFilterMode' => $this->configuration->string(
                'speech.azure.stt.profanity_filter_mode',
            ),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $url = $this->configuration->endpoint()
            .$this->configuration->string('speech.azure.stt.route')
            .'?api-version='.rawurlencode($this->configuration->string(
                'speech.azure.stt.api_version',
            ));
        $started = hrtime(true);

        try {
            $response = $this->http->request(
                $this->configuration->integer(
                    'speech.azure.maximum_json_response_bytes',
                ),
            )
                ->acceptJson()
                ->attach(
                    'audio',
                    $request->audioBytes,
                    'audio.wav',
                    ['Content-Type' => 'audio/wav'],
                )
                ->attach(
                    'definition',
                    $definition,
                    null,
                    ['Content-Type' => 'application/json'],
                )
                ->post($url);
        } catch (ConversationProviderException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw $this->http->fromConnectionFailure(
                $exception,
                $this->elapsed($started),
            );
        } catch (Throwable) {
            throw $this->http->failure(
                'provider_transport_failure',
                $this->elapsed($started),
            );
        }

        $latency = $this->elapsed($started);
        if ($response->status() !== 200) {
            throw $this->http->fromHttpStatus($response->status(), $latency);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_starts_with($contentType, 'application/json')) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        $body = $this->http->boundedBody(
            $response,
            $this->configuration->integer(
                'speech.azure.maximum_json_response_bytes',
            ),
        );

        try {
            $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        if (! is_array($payload)) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        $combined = $payload['combinedPhrases'] ?? null;
        $phrases = $payload['phrases'] ?? null;
        if (
            ! is_array($combined)
            || count($combined) !== 1
            || ! is_array($combined[0] ?? null)
            || ! is_array($phrases)
            || $phrases === []
        ) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        $transcript = trim((string) ($combined[0]['text'] ?? ''));
        if (
            $transcript === ''
            || preg_match('//u', $transcript) !== 1
            || mb_strlen($transcript) > 500
        ) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        $confidenceTotal = 0.0;
        $providerDuration = 0.0;

        foreach ($phrases as $phrase) {
            if (! is_array($phrase)) {
                throw $this->http->failure(
                    'provider_malformed_response',
                    $latency,
                );
            }

            $locale = $phrase['locale'] ?? $payload['locale'] ?? null;
            $confidence = $phrase['confidence'] ?? null;
            $offset = $phrase['offsetMilliseconds'] ?? 0;
            $duration = $phrase['durationMilliseconds'] ?? null;

            if (
                $locale !== $request->language
                || ! is_numeric($confidence)
                || ! is_finite((float) $confidence)
                || (float) $confidence < 0.0
                || (float) $confidence > 1.0
                || ! is_numeric($offset)
                || ! is_numeric($duration)
                || (float) $offset < 0.0
                || (float) $duration <= 0.0
            ) {
                throw $this->http->failure(
                    'provider_malformed_response',
                    $latency,
                );
            }

            $confidenceTotal += (float) $confidence;
            $providerDuration = max(
                $providerDuration,
                (float) $offset + (float) $duration,
            );
        }

        // Azure endpointing reports active speech, which is routinely shorter
        // than the full WAV (leading/trailing silence or noise padding). Fail
        // closed only when the provider claims speech beyond the local media.
        $durationTolerance = max(1000.0, $metadata->durationMs * 0.1);
        if ($providerDuration > $metadata->durationMs + $durationTolerance) {
            throw $this->http->failure('provider_duration_mismatch', $latency);
        }

        return new RecordedSpeechToTextResult(
            transcript: $transcript,
            confidenceBps: (int) round(
                ($confidenceTotal / count($phrases)) * 10000,
            ),
            usage: new ProviderUsage(
                providerKey: 'azure-speech',
                modelVersion: $this->configuration->string(
                    'speech.azure.stt.model_version',
                ),
                latencyMilliseconds: $latency,
                inputUnits: $metadata->durationMs,
                outputUnits: mb_strlen($transcript),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'audio_milliseconds',
                outputUnitKind: 'unicode_characters',
                billableUnitKind: 'audio_milliseconds',
                billableUnitQuantity: $metadata->durationMs,
                apiVersion: $this->configuration->string(
                    'speech.azure.stt.api_version',
                ),
                locale: $this->configuration->string(
                    'speech.azure.stt.locale',
                ),
                region: $this->configuration->string('speech.azure.region'),
                configurationSha256: $this->configuration
                    ->configurationFingerprint(),
            ),
            responseMetadataSha256: $this->metadataHash(
                $response->headers(),
                is_string($payload['modelVersion'] ?? null)
                    ? $payload['modelVersion']
                    : null,
            ),
        );
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private function metadataHash(array $headers, ?string $modelVersion): string
    {
        $safe = [];
        foreach (
            ['x-requestid', 'x-microsoft-requestid', 'apim-request-id', 'date'] as $header
        ) {
            foreach ($headers as $name => $values) {
                if (strtolower($name) === $header) {
                    $safe[$header] = array_map(
                        static fn (string $value): string => hash(
                            'sha256',
                            $value,
                        ),
                        $values,
                    );
                }
            }
        }
        if (
            is_string($modelVersion)
            && preg_match('/\A[a-zA-Z0-9._:-]{1,128}\z/', $modelVersion) === 1
        ) {
            $safe['model_version'] = $modelVersion;
        }
        ksort($safe);

        return hash('sha256', json_encode($safe, JSON_THROW_ON_ERROR));
    }

    private function elapsed(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
