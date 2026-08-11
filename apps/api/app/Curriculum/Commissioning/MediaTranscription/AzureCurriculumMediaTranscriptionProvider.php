<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use App\Curriculum\CanonicalJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use JsonException;
use Normalizer;
use RuntimeException;
use Throwable;

final readonly class AzureCurriculumMediaTranscriptionProvider implements CurriculumMediaTranscriptionProvider
{
    /** @param array<string, mixed> $authorization */
    public function __construct(
        private Factory $http,
        private array $authorization,
    ) {}

    public function transcribe(
        MediaTranscriptionRequest $request,
    ): MediaTranscriptionResult {
        $this->assertRequest($request);
        $definition = json_encode([
            'locales' => ['en-IN'],
            'profanityFilterMode' => 'None',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $started = hrtime(true);

        try {
            $response = $this->http
                ->withHeaders($this->authHeaders())
                ->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'verify' => true,
                    'progress' => static function (
                        int $downloadTotal,
                        int $downloadedBytes,
                        int $uploadTotal,
                        int $uploadedBytes,
                    ): void {
                        unset($uploadTotal, $uploadedBytes);

                        if (
                            $downloadTotal > 1_048_576
                            || $downloadedBytes > 1_048_576
                        ) {
                            throw new RuntimeException('Provider response limit exceeded.');
                        }
                    },
                ])
                ->connectTimeout(3)
                ->timeout(300)
                ->acceptJson()
                ->attach(
                    'audio',
                    $request->audioBytes,
                    'audio.mp3',
                    ['Content-Type' => 'audio/mpeg'],
                )
                ->attach(
                    'definition',
                    $definition,
                    null,
                    ['Content-Type' => 'application/json'],
                )
                ->post($this->url());
        } catch (ConnectionException $exception) {
            throw new MediaTranscriptionProviderException(
                'provider_connection_failure',
                true,
                $this->elapsed($started),
                $exception,
            );
        } catch (MediaTranscriptionProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MediaTranscriptionProviderException(
                'provider_transport_failure',
                false,
                $this->elapsed($started),
                $exception,
            );
        }

        $latency = $this->elapsed($started);
        if ($response->status() !== 200) {
            throw $this->httpFailure($response->status(), $latency);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_starts_with($contentType, 'application/json')) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        $body = $this->boundedBody($response, $latency);
        try {
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
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
            || ($combined[0]['channel'] ?? 0) !== 0
            || ! is_array($phrases)
            || $phrases === []
            || count($phrases) > 4096
        ) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        $transcript = $this->validatedText(
            $combined[0]['text'] ?? null,
            $latency,
            32_000,
            65_536,
        );
        $segments = [];
        $priorEnd = 0;
        $maximumEnd = $request->sourceDurationMilliseconds
            + max(1000, (int) ceil($request->sourceDurationMilliseconds * 0.1));

        foreach ($phrases as $index => $phrase) {
            if (! is_array($phrase)) {
                throw $this->failure('provider_malformed_response', $latency);
            }

            $locale = $phrase['locale'] ?? $payload['locale'] ?? null;
            $confidence = $phrase['confidence'] ?? null;
            $offset = $phrase['offsetMilliseconds'] ?? null;
            $duration = $phrase['durationMilliseconds'] ?? null;
            if (
                $locale !== 'en-IN'
                || ! is_int($offset)
                || ! is_int($duration)
                || $offset < 0
                || $duration < 1
                || $offset < $priorEnd
                || $offset + $duration > $maximumEnd
                || ! is_numeric($confidence)
                || ! is_finite((float) $confidence)
                || (float) $confidence < 0.0
                || (float) $confidence > 1.0
            ) {
                throw $this->failure('provider_malformed_response', $latency);
            }

            $segments[] = new MediaTranscriptionSegment(
                sequence: $index + 1,
                text: $this->validatedText(
                    $phrase['text'] ?? null,
                    $latency,
                    32_000,
                    65_536,
                ),
                offsetMilliseconds: $offset,
                durationMilliseconds: $duration,
                confidenceBps: (int) round((float) $confidence * 10_000),
            );
            $priorEnd = $offset + $duration;
        }

        return new MediaTranscriptionResult(
            transcript: $transcript,
            segments: $segments,
            responseMetadataSha256: $this->metadataHash(
                $response->headers(),
                is_string($payload['modelVersion'] ?? null)
                    ? $payload['modelVersion']
                    : null,
            ),
            latencyMilliseconds: $latency,
            usageMilliseconds: $request->sourceDurationMilliseconds,
        );
    }

    private function assertRequest(MediaTranscriptionRequest $request): void
    {
        if (
            $request->mimeType !== 'audio/mpeg'
            || $request->locale !== 'en-IN'
            || $request->audioBytes === ''
            || $request->sourceDurationMilliseconds < 1
            || preg_match('/\A[0-9a-f]{64}\z/', $request->sourceSha256) !== 1
            || ! hash_equals($request->sourceSha256, hash('sha256', $request->audioBytes))
        ) {
            throw $this->failure('provider_input_invalid');
        }
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        $headers = $this->authorization['auth_header'] ?? null;
        if (! is_array($headers) || count($headers) !== 1) {
            throw $this->failure('provider_not_authorized');
        }

        $name = array_key_first($headers);
        $value = is_string($name) ? $headers[$name] ?? null : null;
        if (
            ! is_string($name)
            || ! in_array($name, ['Authorization', 'Ocp-Apim-Subscription-Key'], true)
            || ! is_string($value)
            || $value === ''
        ) {
            throw $this->failure('provider_not_authorized');
        }

        return [$name => $value];
    }

    private function url(): string
    {
        $endpoint = $this->authorization['endpoint'] ?? null;
        if (! is_string($endpoint) || $endpoint === '') {
            throw $this->failure('provider_not_authorized');
        }

        return $endpoint
            .'/speechtotext/transcriptions:transcribe?api-version=2025-10-15';
    }

    private function boundedBody(Response $response, int $latency): string
    {
        $length = $response->header('Content-Length');
        if ($length !== '' && ctype_digit($length) && (int) $length > 1_048_576) {
            throw $this->failure('provider_response_too_large', $latency);
        }

        $body = $response->body();
        if (strlen($body) > 1_048_576) {
            throw $this->failure('provider_response_too_large', $latency);
        }

        return $body;
    }

    private function validatedText(
        mixed $value,
        int $latency,
        int $maximumScalars,
        int $maximumBytes,
    ): string {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        $normalized = Normalizer::normalize(trim($value), Normalizer::FORM_C);
        if (
            ! is_string($normalized)
            || $normalized === ''
            || ! hash_equals($value, $normalized)
            || mb_strlen($normalized) > $maximumScalars
            || strlen($normalized) > $maximumBytes
        ) {
            throw $this->failure('provider_malformed_response', $latency);
        }

        return $normalized;
    }

    /** @param array<string, list<string>> $headers */
    private function metadataHash(array $headers, ?string $modelVersion): string
    {
        $safe = [];
        foreach (['x-requestid', 'x-microsoft-requestid', 'apim-request-id', 'date'] as $allowed) {
            foreach ($headers as $name => $values) {
                if (strtolower($name) === $allowed) {
                    $safe[$allowed] = array_map(
                        static fn (string $value): string => hash('sha256', $value),
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

        return CanonicalJson::sha256($safe);
    }

    private function httpFailure(int $status, int $latency): MediaTranscriptionProviderException
    {
        $retryable = $status === 408 || $status === 429 || $status >= 500;
        $code = match (true) {
            $status >= 300 && $status < 400 => 'provider_redirect_refused',
            $status === 401 => 'provider_authentication_failed',
            $status === 403 => 'provider_authorization_failed',
            $status === 413 => 'provider_request_too_large',
            $retryable => 'provider_transient_failure',
            default => 'provider_http_failure',
        };

        return $this->failure($code, $latency, $retryable);
    }

    private function failure(
        string $code,
        int $latency = 0,
        bool $retryable = false,
    ): MediaTranscriptionProviderException {
        return new MediaTranscriptionProviderException($code, $retryable, $latency);
    }

    private function elapsed(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
