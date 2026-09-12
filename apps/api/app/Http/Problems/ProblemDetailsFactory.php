<?php

declare(strict_types=1);

namespace App\Http\Problems;

use App\Authentication\Mfa\FreshPrivilegedMfaRequired;
use App\Authentication\Password\FreshPasswordConfirmationRequired;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Observability\CorrelationId;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceConflict;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceRejected;
use App\Operator\EntitlementAdministration\Exceptions\EntitlementAdministrationConflict;
use App\Operator\EntitlementAdministration\Exceptions\EntitlementAdministrationRejected;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationConflict;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ProblemDetailsFactory
{
    public function fromExceptionResponse(
        Request $request,
        Response $response,
        ?Throwable $exception = null,
    ): JsonResponse {
        return $this->make(
            request: $request,
            status: $this->statusForException(
                $response->getStatusCode(),
                $exception,
            ),
            sourceResponse: $response,
            exception: $exception,
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

    public function rangeNotSatisfiable(
        Request $request,
        int $totalBytes,
        string $etag,
    ): JsonResponse {
        return $this->make(
            request: $request,
            status: Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE,
            headers: [
                'Accept-Ranges' => 'bytes',
                'Content-Range' => 'bytes */'.$totalBytes,
                'ETag' => $etag,
            ],
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
        ?Throwable $exception = null,
    ): JsonResponse {
        $definition = $this->definition($status, $exception);
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
    private function definition(int $status, ?Throwable $exception = null): array
    {
        if ($exception instanceof EntitlementDenied) {
            return match ($exception->messageKey) {
                EntitlementDenied::LEVEL_LOCKED => [
                    'type' => 'urn:global-english:problem:entitlement-level-locked',
                    'title' => 'Level requires an upgrade',
                    'detail' => 'This level is not included in the current plan.',
                    'message_key' => EntitlementDenied::LEVEL_LOCKED,
                ],
                EntitlementDenied::ROLEPLAY_LOCKED => [
                    'type' => 'urn:global-english:problem:entitlement-roleplay-locked',
                    'title' => 'Role-play requires an upgrade',
                    'detail' => 'Role-play is not included in the current plan.',
                    'message_key' => EntitlementDenied::ROLEPLAY_LOCKED,
                ],
                EntitlementDenied::DEMO_AI_EXHAUSTED => [
                    'type' => 'urn:global-english:problem:entitlement-demo-ai-exhausted',
                    'title' => 'Demo speaking time used',
                    'detail' => 'The free demo live-AI allowance is used up.',
                    'message_key' => EntitlementDenied::DEMO_AI_EXHAUSTED,
                ],
                EntitlementDenied::AI_EXHAUSTED => [
                    'type' => 'urn:global-english:problem:entitlement-ai-exhausted',
                    'title' => 'Live speaking time used',
                    'detail' => 'This month’s live-AI allowance is used up. Buy talk time or wait for the next month.',
                    'message_key' => EntitlementDenied::AI_EXHAUSTED,
                ],
                EntitlementDenied::TALK_PACK_REQUIRES_PAID => [
                    'type' => 'urn:global-english:problem:entitlement-talk-pack-requires-paid',
                    'title' => 'Talk time needs a paid plan',
                    'detail' => 'Buy Modules or Premium before adding talk time.',
                    'message_key' => EntitlementDenied::TALK_PACK_REQUIRES_PAID,
                ],
                EntitlementDenied::EXPIRED => [
                    'type' => 'urn:global-english:problem:entitlement-expired',
                    'title' => 'Subscription expired',
                    'detail' => 'The current plan is no longer active.',
                    'message_key' => EntitlementDenied::EXPIRED,
                ],
                default => [
                    'type' => 'urn:global-english:problem:entitlement-checkout-unavailable',
                    'title' => 'Checkout unavailable',
                    'detail' => 'The payment could not be completed.',
                    'message_key' => EntitlementDenied::CHECKOUT_UNAVAILABLE,
                ],
            };
        }

        if ($exception instanceof EntitlementAdministrationRejected) {
            return [
                'type' => 'urn:global-english:problem:entitlement-not-found',
                'title' => 'Resource not found',
                'detail' => 'The requested resource could not be found.',
                'message_key' => EntitlementAdministrationRejected::NOT_FOUND,
            ];
        }

        if ($exception instanceof EntitlementAdministrationConflict) {
            return match ($exception->messageKey) {
                EntitlementAdministrationConflict::IDEMPOTENCY => [
                    'type' => 'urn:global-english:problem:entitlement-idempotency-conflict',
                    'title' => 'Idempotency conflict',
                    'detail' => 'The idempotency key is already bound to another request.',
                    'message_key' => EntitlementAdministrationConflict::IDEMPOTENCY,
                ],
                default => [
                    'type' => 'urn:global-english:problem:entitlement-stale-state',
                    'title' => 'Resource state changed',
                    'detail' => 'Reload the resource and retry the request.',
                    'message_key' => EntitlementAdministrationConflict::STALE,
                ],
            };
        }

        if ($exception instanceof MediaLibraryConflict) {
            return match ($exception->messageKey) {
                MediaLibraryConflict::IDEMPOTENCY => [
                    'type' => 'urn:global-english:problem:media-library-idempotency-conflict',
                    'title' => 'Idempotency conflict',
                    'detail' => 'The idempotency key is already bound to another request.',
                    'message_key' => MediaLibraryConflict::IDEMPOTENCY,
                ],
                MediaLibraryConflict::STALE_UPLOAD => [
                    'type' => 'urn:global-english:problem:media-library-stale-upload',
                    'title' => 'Upload state changed',
                    'detail' => 'Reload the upload and retry the request.',
                    'message_key' => MediaLibraryConflict::STALE_UPLOAD,
                ],
                MediaLibraryConflict::STALE_METADATA => [
                    'type' => 'urn:global-english:problem:media-library-stale-metadata',
                    'title' => 'Metadata head changed',
                    'detail' => 'Reload the metadata head and retry the request.',
                    'message_key' => MediaLibraryConflict::STALE_METADATA,
                ],
                MediaLibraryConflict::STALE_WORKSPACE => [
                    'type' => 'urn:global-english:problem:media-library-stale-workspace',
                    'title' => 'Workspace version changed',
                    'detail' => 'Reload the content workspace and retry the request.',
                    'message_key' => MediaLibraryConflict::STALE_WORKSPACE,
                ],
                MediaLibraryConflict::STALE_LIFECYCLE => [
                    'type' => 'urn:global-english:problem:media-library-stale-lifecycle',
                    'title' => 'Media lifecycle changed',
                    'detail' => 'Reload the media asset and retry the request.',
                    'message_key' => MediaLibraryConflict::STALE_LIFECYCLE,
                ],
                MediaLibraryConflict::DUPLICATE_USE => [
                    'type' => 'urn:global-english:problem:media-library-duplicate-use',
                    'title' => 'Media relationship already exists',
                    'detail' => 'This usage or replacement relationship already exists.',
                    'message_key' => MediaLibraryConflict::DUPLICATE_USE,
                ],
                MediaLibraryConflict::STORAGE => [
                    'type' => 'urn:global-english:problem:media-library-storage-failure',
                    'title' => 'Private media storage unavailable',
                    'detail' => 'The private media operation did not complete. It can be retried safely.',
                    'message_key' => MediaLibraryConflict::STORAGE,
                ],
                default => [
                    'type' => 'urn:global-english:problem:media-library-concurrent-change',
                    'title' => 'Concurrent media change',
                    'detail' => 'Another media command completed first. Reload and retry.',
                    'message_key' => MediaLibraryConflict::CONCURRENT_CHANGE,
                ],
            };
        }

        if ($exception instanceof MediaLibraryRejected) {
            return [
                'type' => 'urn:global-english:problem:media-library-not-found',
                'title' => 'Resource not found',
                'detail' => 'The requested resource could not be found.',
                'message_key' => MediaLibraryRejected::NOT_FOUND,
            ];
        }

        if ($exception instanceof ContentWorkspaceConflict) {
            return match ($exception->messageKey) {
                ContentWorkspaceConflict::IDEMPOTENCY => [
                    'type' => 'urn:global-english:problem:content-workspace-idempotency-conflict',
                    'title' => 'Idempotency conflict',
                    'detail' => 'The idempotency key is already bound to another request.',
                    'message_key' => ContentWorkspaceConflict::IDEMPOTENCY,
                ],
                ContentWorkspaceConflict::STATE => [
                    'type' => 'urn:global-english:problem:content-workspace-state-conflict',
                    'title' => 'Workspace state conflict',
                    'detail' => 'The content workspace cannot accept this action in its current state.',
                    'message_key' => ContentWorkspaceConflict::STATE,
                ],
                ContentWorkspaceConflict::HEAD => [
                    'type' => 'urn:global-english:problem:content-workspace-head-conflict',
                    'title' => 'Content head changed',
                    'detail' => 'Reload the current content head and retry the request.',
                    'message_key' => ContentWorkspaceConflict::HEAD,
                ],
                ContentWorkspaceConflict::CONCURRENT_CHANGE => [
                    'type' => 'urn:global-english:problem:content-workspace-concurrent-change',
                    'title' => 'Concurrent content change',
                    'detail' => 'Another content command completed first. Reload and retry.',
                    'message_key' => ContentWorkspaceConflict::CONCURRENT_CHANGE,
                ],
                default => [
                    'type' => 'urn:global-english:problem:content-workspace-stale-version',
                    'title' => 'Workspace version changed',
                    'detail' => 'Reload the content workspace and retry the request.',
                    'message_key' => ContentWorkspaceConflict::STALE_VERSION,
                ],
            };
        }

        if ($exception instanceof ContentWorkspaceRejected) {
            return [
                'type' => 'urn:global-english:problem:content-workspace-not-found',
                'title' => 'Resource not found',
                'detail' => 'The requested resource could not be found.',
                'message_key' => ContentWorkspaceRejected::NOT_FOUND,
            ];
        }

        if ($exception instanceof LearnerAdministrationConflict) {
            return match ($exception->messageKey) {
                LearnerAdministrationConflict::IDEMPOTENCY => [
                    'type' => 'urn:global-english:problem:learner-administration-idempotency-conflict',
                    'title' => 'Idempotency conflict',
                    'detail' => 'The idempotency key is already bound to another request.',
                    'message_key' => LearnerAdministrationConflict::IDEMPOTENCY,
                ],
                LearnerAdministrationConflict::ACTIVE_DEPENDENCY => [
                    'type' => 'urn:global-english:problem:learner-administration-active-dependency',
                    'title' => 'Active dependency',
                    'detail' => 'An active learner relationship prevents this change.',
                    'message_key' => LearnerAdministrationConflict::ACTIVE_DEPENDENCY,
                ],
                LearnerAdministrationConflict::UNSAFE_ROLE => [
                    'type' => 'urn:global-english:problem:learner-administration-unsafe-role',
                    'title' => 'Learner role is not safe',
                    'detail' => 'The selected role does not have the exact learner permission set.',
                    'message_key' => LearnerAdministrationConflict::UNSAFE_ROLE,
                ],
                default => [
                    'type' => 'urn:global-english:problem:learner-administration-stale-state',
                    'title' => 'Learner state changed',
                    'detail' => 'Reload the learner resource and retry the request.',
                    'message_key' => LearnerAdministrationConflict::STALE_STATE,
                ],
            };
        }

        if ($exception instanceof OrganizationAdministrationConflict) {
            return match ($exception->messageKey) {
                OrganizationAdministrationConflict::IDEMPOTENCY => [
                    'type' => 'urn:global-english:problem:organization-administration-idempotency-conflict',
                    'title' => 'Idempotency conflict',
                    'detail' => 'The idempotency key is already bound to another request.',
                    'message_key' => OrganizationAdministrationConflict::IDEMPOTENCY,
                ],
                OrganizationAdministrationConflict::LAST_MANAGER => [
                    'type' => 'urn:global-english:problem:organization-administration-last-manager',
                    'title' => 'Manager authority required',
                    'detail' => 'The organization must retain an active authorized manager.',
                    'message_key' => OrganizationAdministrationConflict::LAST_MANAGER,
                ],
                OrganizationAdministrationConflict::ACTIVE_DEPENDENCY => [
                    'type' => 'urn:global-english:problem:organization-administration-active-dependency',
                    'title' => 'Active dependency',
                    'detail' => 'An active related resource prevents this change.',
                    'message_key' => OrganizationAdministrationConflict::ACTIVE_DEPENDENCY,
                ],
                default => [
                    'type' => 'urn:global-english:problem:organization-administration-stale-state',
                    'title' => 'Resource state changed',
                    'detail' => 'Reload the resource and retry the request.',
                    'message_key' => OrganizationAdministrationConflict::STALE_STATE,
                ],
            };
        }

        if (
            $exception instanceof OrganizationAdministrationRejected
            && $exception->messageKey === OrganizationAdministrationRejected::SELF_TARGET
        ) {
            return [
                'type' => 'urn:global-english:problem:organization-administration-self-target',
                'title' => 'Self-targeting is not permitted',
                'detail' => 'This administrative action cannot target the current membership.',
                'message_key' => OrganizationAdministrationRejected::SELF_TARGET,
            ];
        }

        if ($this->hasCause($exception, FreshPrivilegedMfaRequired::class)) {
            return [
                'type' => 'urn:global-english:problem:fresh-mfa-required',
                'title' => 'Fresh verification required',
                'detail' => 'A recent authenticator-code verification is required.',
                'message_key' => 'problem.fresh_mfa_required',
            ];
        }

        if ($this->hasCause($exception, FreshPasswordConfirmationRequired::class)) {
            return [
                'type' => 'urn:global-english:problem:fresh-password-confirmation-required',
                'title' => 'Fresh verification required',
                'detail' => 'Re-enter the current password to continue.',
                'message_key' => 'problem.fresh_password_confirmation_required',
            ];
        }

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
            Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE => [
                'type' => 'urn:global-english:problem:range-not-satisfiable',
                'title' => 'Range not satisfiable',
                'detail' => 'The requested byte range is outside this resource.',
                'message_key' => 'problem.range_not_satisfiable',
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

    /**
     * @param  class-string<Throwable>  $class
     */
    private function hasCause(?Throwable $exception, string $class): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if ($current instanceof $class) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function statusForException(
        int $responseStatus,
        ?Throwable $exception,
    ): int {
        if (
            $this->hasCause($exception, FreshPrivilegedMfaRequired::class)
            || $this->hasCause($exception, FreshPasswordConfirmationRequired::class)
        ) {
            return Response::HTTP_FORBIDDEN;
        }

        return $responseStatus;
    }
}
