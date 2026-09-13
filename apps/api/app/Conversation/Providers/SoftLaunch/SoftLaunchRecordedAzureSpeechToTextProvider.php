<?php

declare(strict_types=1);

namespace App\Conversation\Providers\SoftLaunch;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use App\Conversation\Voice\VoiceOperationRejected;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Soft-launch Azure Speech STT for consented learner recordings (APP-0068).
 */
final readonly class SoftLaunchRecordedAzureSpeechToTextProvider implements RecordedSpeechToTextProvider
{
    public function __construct(
        private SoftLaunchRecordedSpeechConfiguration $configuration,
        private Factory $http,
        private PcmWavInspector $wavInspector,
    ) {}

    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        $this->configuration->assertLiveAuthorized();

        if (
            $request->mimeType !== 'audio/wav'
            || $request->language !== $this->configuration->locale()
        ) {
            throw $this->failure('provider_policy_failure');
        }

        try {
            $metadata = $this->wavInspector->inspect($request->audioBytes);
        } catch (VoiceOperationRejected) {
            throw $this->failure('provider_input_invalid');
        }

        $definition = json_encode([
            'locales' => [$request->language],
            'profanityFilterMode' => 'None',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $started = hrtime(true);
        $maximumBytes = $this->configuration->maximumJsonResponseBytes();

        try {
            $response = $this->http
                ->withHeaders([
                    ...$this->configuration->authenticationHeaders(),
                    'User-Agent' => 'global-spoken-english-soft-launch-stt',
                ])
                ->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'verify' => true,
                    'progress' => static function (
                        int $downloadTotal,
                        int $downloadedBytes,
                        int $uploadTotal,
                        int $uploadedBytes,
                    ) use ($maximumBytes): void {
                        unset($uploadTotal, $uploadedBytes);

                        if (
                            $downloadTotal > $maximumBytes
                            || $downloadedBytes > $maximumBytes
                        ) {
                            throw new RuntimeException(
                                'The provider response exceeded its byte limit.',
                            );
                        }
                    },
                ])
                ->connectTimeout($this->configuration->connectionTimeoutSeconds())
                ->timeout($this->configuration->requestTimeoutSeconds())
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
                ->post($this->configuration->transcribeUrl());
        } catch (ConversationProviderException $exception) {
            throw $exception;
        } catch (ConnectionException) {
            throw $this->failure('provider_connection_failure', $this->elapsed($started));
        } catch (Throwable) {
            throw $this->failure('provider_transport_failure', $this->elapsed($started));
        }

        $latency = $this->elapsed($started);
        if ($response->status() !== 200) {
            throw $this->failure('provider_http_failure', $latency);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_starts_with($contentType, 'application/json')) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > $maximumBytes) {
            throw $this->failure('provider_response_too_large', $latency);
        }

        try {
            $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        if (! is_array($payload)) {
            throw $this->failure('provider_malformed_response', $latency);
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
            throw $this->failure('provider_malformed_response', $latency);
        }

        $transcript = trim((string) ($combined[0]['text'] ?? ''));
        if (
            $transcript === ''
            || preg_match('//u', $transcript) !== 1
            || mb_strlen($transcript) > 500
        ) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        $confidenceTotal = 0.0;
        $providerDuration = 0.0;

        foreach ($phrases as $phrase) {
            if (! is_array($phrase)) {
                throw $this->failure('provider_malformed_response', $latency);
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
                throw $this->failure('provider_malformed_response', $latency);
            }

            $confidenceTotal += (float) $confidence;
            $providerDuration = max(
                $providerDuration,
                (float) $offset + (float) $duration,
            );
        }

        $durationTolerance = max(1000.0, $metadata->durationMs * 0.1);
        if ($providerDuration > $metadata->durationMs + $durationTolerance) {
            throw $this->failure('provider_duration_mismatch', $latency);
        }

        return new RecordedSpeechToTextResult(
            transcript: $transcript,
            confidenceBps: (int) round(
                ($confidenceTotal / count($phrases)) * 10000,
            ),
            usage: new ProviderUsage(
                providerKey: $this->configuration->providerKey(),
                modelVersion: $this->configuration->modelVersion(),
                latencyMilliseconds: $latency,
                inputUnits: $metadata->durationMs,
                outputUnits: mb_strlen($transcript),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'audio_milliseconds',
                outputUnitKind: 'unicode_characters',
                billableUnitKind: 'audio_milliseconds',
                billableUnitQuantity: $metadata->durationMs,
                locale: $this->configuration->locale(),
            ),
        );
    }

    private function failure(
        string $safeCode,
        int $latencyMilliseconds = 0,
    ): ConversationProviderException {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: $this->configuration->providerKey(),
            modelVersion: $this->configuration->modelVersion(),
            latencyMilliseconds: $latencyMilliseconds,
        );
    }

    private function elapsed(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
