<?php

declare(strict_types=1);

namespace App\Http\Problems;

use App\Observability\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProblemDetailsFactory
{
    public function fromExceptionResponse(Request $request, Response $response): JsonResponse
    {
        return $this->make(
            request: $request,
            status: $response->getStatusCode(),
            sourceResponse: $response,
        );
    }

    public function serviceUnavailable(Request $request): JsonResponse
    {
        return $this->make(
            request: $request,
            status: Response::HTTP_SERVICE_UNAVAILABLE,
            headers: ['Retry-After' => '5'],
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function make(
        Request $request,
        int $status,
        ?Response $sourceResponse = null,
        array $headers = [],
    ): JsonResponse {
        $definition = $this->definition($status);
        $correlationId = CorrelationId::forRequest($request);

        foreach (['Allow', 'Retry-After', 'WWW-Authenticate'] as $header) {
            if ($sourceResponse?->headers->has($header) === true) {
                $value = $sourceResponse->headers->get($header);

                if (is_string($value)) {
                    $headers[$header] = $value;
                }
            }
        }

        $headers['Cache-Control'] = 'no-store';
        $headers['Content-Type'] = 'application/problem+json';
        $headers[CorrelationId::HEADER] = $correlationId;

        return new JsonResponse([
            'type' => $definition['type'],
            'title' => $definition['title'],
            'status' => $status,
            'detail' => $definition['detail'],
            'instance' => 'urn:global-english:problem-instance:'.$correlationId,
            'message_key' => $definition['message_key'],
            'correlation_id' => $correlationId,
        ], $status, $headers);
    }

    /**
     * @return array{type: string, title: string, detail: string, message_key: string}
     */
    private function definition(int $status): array
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => [
                'type' => 'urn:global-english:problem:bad-request',
                'title' => 'Bad request',
                'detail' => 'The request could not be processed.',
                'message_key' => 'problem.bad_request',
            ],
            Response::HTTP_UNAUTHORIZED => [
                'type' => 'urn:global-english:problem:unauthenticated',
                'title' => 'Authentication required',
                'detail' => 'Authentication is required to complete this request.',
                'message_key' => 'problem.unauthenticated',
            ],
            Response::HTTP_FORBIDDEN => [
                'type' => 'urn:global-english:problem:forbidden',
                'title' => 'Request forbidden',
                'detail' => 'The request is not permitted.',
                'message_key' => 'problem.forbidden',
            ],
            Response::HTTP_NOT_FOUND => [
                'type' => 'urn:global-english:problem:not-found',
                'title' => 'Resource not found',
                'detail' => 'The requested resource could not be found.',
                'message_key' => 'problem.not_found',
            ],
            Response::HTTP_METHOD_NOT_ALLOWED => [
                'type' => 'urn:global-english:problem:method-not-allowed',
                'title' => 'Method not allowed',
                'detail' => 'The request method is not supported for this resource.',
                'message_key' => 'problem.method_not_allowed',
            ],
            Response::HTTP_CONFLICT => [
                'type' => 'urn:global-english:problem:conflict',
                'title' => 'Request conflict',
                'detail' => 'The request conflicts with the current resource state.',
                'message_key' => 'problem.conflict',
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY => [
                'type' => 'urn:global-english:problem:validation-failed',
                'title' => 'Validation failed',
                'detail' => 'One or more request values are invalid.',
                'message_key' => 'problem.validation_failed',
            ],
            Response::HTTP_TOO_MANY_REQUESTS => [
                'type' => 'urn:global-english:problem:too-many-requests',
                'title' => 'Too many requests',
                'detail' => 'The request rate limit has been exceeded.',
                'message_key' => 'problem.too_many_requests',
            ],
            Response::HTTP_SERVICE_UNAVAILABLE => [
                'type' => 'urn:global-english:problem:service-unavailable',
                'title' => 'Service unavailable',
                'detail' => 'The service is temporarily unable to handle the request.',
                'message_key' => 'problem.service_unavailable',
            ],
            default => $status >= 500
                ? [
                    'type' => 'urn:global-english:problem:internal-error',
                    'title' => 'Internal server error',
                    'detail' => 'The request could not be completed.',
                    'message_key' => 'problem.internal_error',
                ]
                : [
                    'type' => 'urn:global-english:problem:request-failed',
                    'title' => 'Request failed',
                    'detail' => 'The request could not be completed.',
                    'message_key' => 'problem.request_failed',
                ],
        };
    }
}
