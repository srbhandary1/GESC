<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\AcceptInstitutionInviteRequest;
use App\Identity\CurrentIdentityPresenter;
use App\Observability\CorrelationId;
use App\Tenancy\Exceptions\FoundationConfigurationException;
use App\Tenancy\Exceptions\IdempotencyKeyConflict;
use App\Tenancy\Exceptions\InstitutionInviteUnavailable;
use App\Tenancy\InstitutionInviteAcceptor;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class InstitutionInviteController extends Controller
{
    public function __construct(
        private readonly InstitutionInviteAcceptor $invites,
        private readonly CurrentIdentityPresenter $presenter,
    ) {}

    public function accept(
        AcceptInstitutionInviteRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $code = (string) $request->validated('code');
        $idempotencyKey = (string) $request->validated('idempotency_key');

        try {
            $result = $this->invites->accept(
                rawCode: $code,
                user: $principal->user,
                correlationId: CorrelationId::forRequest($request),
                idempotencyKey: $idempotencyKey,
            );
        } catch (InstitutionInviteUnavailable) {
            abort(404);
        } catch (IdempotencyKeyConflict|FoundationConfigurationException) {
            abort(409);
        }

        return response()->json([
            'membership' => $this->presenter->membership(
                $principal->user,
                (string) $result->membership->getKey(),
            ),
            'enrollment' => $result->enrollment === null
                ? null
                : [
                    'id' => (string) $result->enrollment->getKey(),
                    'status' => (string) $result->enrollment->getAttribute('status'),
                ],
            'replayed' => $result->replayed,
        ], $result->replayed
            ? Response::HTTP_OK
            : Response::HTTP_CREATED)
            ->header('Cache-Control', 'no-store');
    }
}
