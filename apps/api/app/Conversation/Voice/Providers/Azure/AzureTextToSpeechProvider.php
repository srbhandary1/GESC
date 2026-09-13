<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\Azure;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\VoiceOperationRejected;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

final readonly class AzureTextToSpeechProvider implements TextToSpeechProvider
{
    public function __construct(
        private AzureSpeechConfiguration $configuration,
        private AzureSpeechHttpBoundary $http,
        private PcmWavInspector $wavInspector,
    ) {}

    public function synthesize(
        TextToSpeechRequest $request,
    ): TextToSpeechResult {
        $this->configuration->assertLiveAuthorized();
        $text = trim($request->text);
        $voices = config('speech.azure.tts.fixture_voices', []);

        if (
            $text === ''
            || preg_match('//u', $text) !== 1
            || mb_strlen($text) > $this->configuration->integer(
                'speech.azure.tts.maximum_characters',
            )
            || ! is_array($voices)
            || ! in_array($request->voice, $voices, true)
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1
        ) {
            throw $this->http->failure('provider_input_invalid');
        }

        $locale = $this->configuration->string('speech.azure.tts.locale');
        $ssml = sprintf(
            '<speak version="1.0" xml:lang="%s"><voice name="%s">%s</voice></speak>',
            htmlspecialchars($locale, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            htmlspecialchars($request->voice, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8'),
        );
        $started = hrtime(true);

        try {
            $response = $this->http->request(
                $this->configuration->integer(
                    'speech.azure.maximum_audio_response_bytes',
                ),
            )
                ->withHeaders([
                    'Content-Type' => 'application/ssml+xml',
                    'X-Microsoft-OutputFormat' => $this->configuration->string(
                        'speech.azure.tts.output_format',
                    ),
                    'User-Agent' => 'global-spoken-english-pr009-benchmark',
                ])
                ->accept('audio/wav')
                ->withBody($ssml, 'application/ssml+xml')
                ->post(
                    $this->configuration->endpoint()
                    .$this->configuration->string('speech.azure.tts.route'),
                );
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
        if (
            ! str_starts_with($contentType, 'audio/wav')
            && ! str_starts_with($contentType, 'audio/x-wav')
            && ! str_starts_with($contentType, 'application/octet-stream')
        ) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        $audio = $this->http->boundedBody(
            $response,
            $this->configuration->integer(
                'speech.azure.maximum_audio_response_bytes',
            ),
        );

        try {
            $metadata = $this->wavInspector->inspect($audio);
        } catch (VoiceOperationRejected) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        if (
            $metadata->sampleRateHz !== 24000
            || $metadata->channels !== 1
            || $metadata->codec !== 'pcm_s16le'
        ) {
            throw $this->http->failure('provider_malformed_response', $latency);
        }

        return new TextToSpeechResult(
            playbackAvailable: true,
            renderingMode: 'azure-private-wav',
            providerUsage: new ProviderUsage(
                providerKey: 'azure-speech',
                modelVersion: $this->configuration->string(
                    'speech.azure.tts.model_version',
                ).':'.$request->voice,
                latencyMilliseconds: $latency,
                inputUnits: mb_strlen($text),
                outputUnits: strlen($audio),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'unicode_characters',
                outputUnitKind: 'audio_bytes',
                billableUnitKind: 'unicode_characters',
                billableUnitQuantity: mb_strlen($text),
                voiceKey: $request->voice,
                locale: $locale,
                region: $this->configuration->string('speech.azure.region'),
                configurationSha256: $this->configuration
                    ->configurationFingerprint(),
            ),
            audioBytes: $audio,
            mimeType: 'audio/wav',
            responseMetadataSha256: $this->metadataHash($response->headers()),
        );
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private function metadataHash(array $headers): string
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
        ksort($safe);

        return hash('sha256', json_encode($safe, JSON_THROW_ON_ERROR));
    }

    private function elapsed(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
