<?php

declare(strict_types=1);

namespace App\Conversation\Providers\SoftLaunch;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\TextFirstTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Conversation\Support\GuidanceTtsSsml;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\VoiceOperationRejected;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use RuntimeException;
use Throwable;

/**
 * Soft-launch Azure Speech TTS for GuidanceTtsPolicy voices (APP-0064).
 *
 * Text remains primary: Azure failures fall back to TextFirstTextToSpeechProvider.
 */
final readonly class SoftLaunchGuidanceAzureTextToSpeechProvider implements TextToSpeechProvider
{
    public function __construct(
        private SoftLaunchGuidanceTtsConfiguration $configuration,
        private Factory $http,
        private PcmWavInspector $wavInspector,
        private TextFirstTextToSpeechProvider $textFirst,
    ) {}

    public function synthesize(
        TextToSpeechRequest $request,
    ): TextToSpeechResult {
        if (
            trim($request->text) === ''
            || ! in_array($request->purpose, GuidanceTtsPolicy::purposes(), true)
            || ! GuidanceTtsPolicy::isAllowlistedVoice($request->voice)
            || $request->voice !== GuidanceTtsPolicy::voiceFor(
                $request->purpose,
                $request->uiLocale,
            )
        ) {
            throw new ConversationProviderException(
                safeCode: 'guidance_tts_contract_mismatch',
                providerKey: $this->configuration->providerKey(),
                modelVersion: $this->configuration->modelVersionBase(),
            );
        }

        try {
            return $this->synthesizeAzure($request);
        } catch (Throwable) {
            if (! GuidanceTtsPolicy::textFirstFallbackRequired()) {
                throw new ConversationProviderException(
                    safeCode: 'provider_transport_failure',
                    providerKey: $this->configuration->providerKey(),
                    modelVersion: $this->configuration->modelVersionBase(),
                );
            }

            return $this->textFirst->synthesize($request);
        }
    }

    private function synthesizeAzure(
        TextToSpeechRequest $request,
    ): TextToSpeechResult {
        $this->configuration->assertLiveAuthorized();
        $text = trim($request->text);
        $maximumCharacters = $this->configuration->maximumCharacters();

        if (
            preg_match('//u', $text) !== 1
            || mb_strlen($text) > $maximumCharacters
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1
        ) {
            throw $this->failure('provider_input_invalid');
        }

        $locale = $this->configuration->localeForVoice($request->voice);
        $embeddedLatinVoice = $request->purpose === GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1
            && $locale !== 'en-IN'
            && preg_match('/\p{Latin}/u', $text) === 1
                ? GuidanceTtsPolicy::voiceFor(
                    GuidanceTtsPolicy::PURPOSE_MODEL_EN,
                    'en-IN',
                )
                : null;
        $ssml = GuidanceTtsSsml::document(
            $locale,
            $request->voice,
            $text,
            $embeddedLatinVoice,
        );
        $started = hrtime(true);
        $maximumBytes = $this->configuration->maximumAudioResponseBytes();

        try {
            $response = $this->http
                ->withHeaders([
                    ...$this->configuration->authenticationHeaders(),
                    'Content-Type' => 'application/ssml+xml',
                    'X-Microsoft-OutputFormat' => $this->configuration->outputFormat(),
                    'User-Agent' => 'global-spoken-english-soft-launch-guidance-tts',
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
                ->accept('audio/wav')
                ->withBody($ssml, 'application/ssml+xml')
                ->post(
                    $this->configuration->endpoint()
                    .$this->configuration->ttsRoute(),
                );
        } catch (ConversationProviderException $exception) {
            throw $exception;
        } catch (ConnectionException) {
            throw $this->failure(
                'provider_connection_failure',
                $this->elapsed($started),
            );
        } catch (Throwable) {
            throw $this->failure(
                'provider_transport_failure',
                $this->elapsed($started),
            );
        }

        $latency = $this->elapsed($started);

        if ($response->status() !== 200) {
            throw $this->failure('provider_http_failure', $latency);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (
            ! str_starts_with($contentType, 'audio/wav')
            && ! str_starts_with($contentType, 'audio/x-wav')
            && ! str_starts_with($contentType, 'application/octet-stream')
        ) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        $audio = $response->body();

        if ($audio === '' || strlen($audio) > $maximumBytes) {
            throw $this->failure('provider_response_too_large', $latency);
        }

        try {
            $metadata = $this->wavInspector->inspect($audio);
        } catch (VoiceOperationRejected) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        if (
            $metadata->sampleRateHz !== 24000
            || $metadata->channels !== 1
            || $metadata->codec !== 'pcm_s16le'
        ) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        $characters = mb_strlen($text);

        return new TextToSpeechResult(
            playbackAvailable: true,
            renderingMode: 'azure-soft-launch-guidance-wav',
            providerUsage: new ProviderUsage(
                providerKey: $this->configuration->providerKey(),
                modelVersion: $this->configuration->modelVersionBase()
                    .':'.$request->voice
                    .($embeddedLatinVoice === null
                        ? ''
                        : '+'.$embeddedLatinVoice),
                latencyMilliseconds: $latency,
                inputUnits: $characters,
                outputUnits: strlen($audio),
                estimatedCostMicrounits: $this->configuration
                    ->estimatedCostMicrounits($characters),
                inputUnitKind: 'unicode_characters',
                outputUnitKind: 'audio_bytes',
                billableUnitKind: 'unicode_characters',
                billableUnitQuantity: $characters,
                voiceKey: $request->voice,
                locale: $this->configuration->localeForVoice($request->voice),
            ),
            audioBytes: $audio,
            mimeType: 'audio/wav',
        );
    }

    private function failure(
        string $safeCode,
        int $latencyMilliseconds = 0,
    ): ConversationProviderException {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: $this->configuration->providerKey(),
            modelVersion: $this->configuration->modelVersionBase(),
            latencyMilliseconds: $latencyMilliseconds,
        );
    }

    private function elapsed(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
