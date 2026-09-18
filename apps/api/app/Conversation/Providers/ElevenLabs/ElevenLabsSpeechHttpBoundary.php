<?php

declare(strict_types=1);

namespace App\Conversation\Providers\ElevenLabs;

use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

final readonly class ElevenLabsSpeechHttpBoundary
{
    public function __construct(
        private Factory $http,
        private ElevenLabsSpeechConfiguration $configuration,
    ) {}

    public function request(
        string $candidateKey,
        string $stage,
        int $maximumResponseBytes,
    ): PendingRequest {
        $this->configuration->assertAuthorized($candidateKey, $stage);
        if ($maximumResponseBytes < 1) {
            throw $this->failure($candidateKey, 'provider_policy_failure');
        }

        return $this->http
            ->withHeaders($this->configuration->authenticationHeaders($candidateKey))
            ->withOptions([
                'allow_redirects' => false,
                'http_errors' => false,
                'verify' => true,
                'progress' => static function (
                    int $downloadTotal,
                    int $downloadedBytes,
                    int $uploadTotal,
                    int $uploadedBytes,
                ) use ($maximumResponseBytes): void {
                    unset($uploadTotal, $uploadedBytes);
                    if ($downloadTotal > $maximumResponseBytes || $downloadedBytes > $maximumResponseBytes) {
                        throw new RuntimeException('The provider response exceeded its byte limit.');
                    }
                },
            ])
            ->connectTimeout($this->configuration->integer(
                $candidateKey,
                'connection_timeout_seconds',
            ))
            ->timeout($this->configuration->integer(
                $candidateKey,
                'request_timeout_seconds',
            ));
    }

    public function boundedBody(
        string $candidateKey,
        Response $response,
        int $maximumBytes,
    ): string {
        $contentLength = $response->header('Content-Length');
        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $maximumBytes) {
            throw $this->failure($candidateKey, 'provider_response_too_large');
        }
        $body = $response->body();
        if ($body === '' || strlen($body) > $maximumBytes) {
            throw $this->failure($candidateKey, 'provider_response_too_large');
        }

        return $body;
    }

    public function fromStatus(
        string $candidateKey,
        int $status,
        int $latencyMilliseconds,
    ): ConversationProviderException {
        $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true);
        $safeCode = match (true) {
            $status >= 300 && $status < 400 => 'provider_redirect_refused',
            $status === 401 => 'provider_authentication_failed',
            $status === 403 => 'provider_authorization_failed',
            $status === 413 => 'provider_request_too_large',
            $retryable => 'provider_transient_failure',
            default => 'provider_http_failure',
        };

        return $this->failure($candidateKey, $safeCode, $latencyMilliseconds, $retryable);
    }

    public function failure(
        string $candidateKey,
        string $safeCode,
        int $latencyMilliseconds = 0,
        bool $retryable = false,
    ): ConversationProviderException {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: $candidateKey,
            modelVersion: $this->configuration->string($candidateKey, 'api_model'),
            latencyMilliseconds: $latencyMilliseconds,
            retryable: $retryable,
        );
    }
}
