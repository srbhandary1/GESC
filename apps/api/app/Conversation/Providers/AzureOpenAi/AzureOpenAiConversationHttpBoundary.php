<?php

declare(strict_types=1);

namespace App\Conversation\Providers\AzureOpenAi;

use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

final readonly class AzureOpenAiConversationHttpBoundary
{
    public function __construct(
        private Factory $http,
        private AzureOpenAiConversationConfiguration $configuration,
    ) {}

    public function request(int $maximumResponseBytes): PendingRequest
    {
        $this->configuration->assertLiveAuthorized();
        if ($maximumResponseBytes < 1) {
            throw $this->configuration->failure('provider_policy_failure');
        }

        return $this->http
            ->withHeaders($this->configuration->authenticationHeaders())
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

                    if (
                        $downloadTotal > $maximumResponseBytes
                        || $downloadedBytes > $maximumResponseBytes
                    ) {
                        throw new RuntimeException(
                            'The provider response exceeded its byte limit.',
                        );
                    }
                },
            ])
            ->connectTimeout($this->configuration->integer(
                'conversation.azure_openai.connection_timeout_seconds',
            ))
            ->timeout($this->configuration->integer(
                'conversation.azure_openai.request_timeout_seconds',
            ));
    }

    public function boundedBody(Response $response, int $maximumBytes): string
    {
        if ($maximumBytes < 1) {
            throw $this->configuration->failure('provider_policy_failure');
        }

        $contentLength = $response->header('Content-Length');
        if (
            $contentLength !== ''
            && ctype_digit($contentLength)
            && (int) $contentLength > $maximumBytes
        ) {
            throw $this->configuration->failure('provider_response_too_large');
        }

        $body = $response->body();

        if (strlen($body) > $maximumBytes) {
            throw $this->configuration->failure('provider_response_too_large');
        }

        return $body;
    }

    public function fromConnectionFailure(
        ConnectionException $exception,
        int $latencyMilliseconds,
    ): ConversationProviderException {
        unset($exception);

        return $this->configuration->failure(
            'provider_connection_failure',
            $latencyMilliseconds,
            true,
        );
    }

    public function fromHttpStatus(
        int $status,
        int $latencyMilliseconds,
    ): ConversationProviderException {
        $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true);
        $safeCode = match (true) {
            $status >= 300 && $status < 400 => 'provider_redirect_refused',
            $status === 401 => 'provider_authentication_failed',
            $status === 403 => 'provider_authorization_failed',
            $status === 413 => 'provider_request_too_large',
            $status === 429 => 'provider_rate_limited',
            $retryable => 'provider_transient_failure',
            default => 'provider_http_failure',
        };

        return $this->configuration->failure(
            $safeCode,
            $latencyMilliseconds,
            $retryable,
        );
    }
}
