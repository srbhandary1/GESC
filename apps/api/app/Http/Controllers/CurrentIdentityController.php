<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\SelectOrganizationContextRequest;
use App\Identity\CurrentIdentityPresenter;
use App\Identity\OrganizationSelectionStore;
use App\Tenancy\Exceptions\InvalidOrganizationContext;
use App\Tenancy\OrganizationContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentIdentityController extends Controller
{
    public function __construct(
        private readonly OrganizationContextResolver $contexts,
        private readonly OrganizationSelectionStore $selections,
        private readonly CurrentIdentityPresenter $presenter,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $selectedMembershipId = $this->selections->selected(
            $request,
            $principal,
        );
        $response = $this->presenter->forUser(
            $principal->user,
            $selectedMembershipId,
        );

        if (
            $selectedMembershipId !== null
            && $response['current_membership'] === null
        ) {
            $this->selections->clear($request, $principal);
        }

        return response()->json($response)
            ->header('Cache-Control', 'no-store');
    }

    public function select(
        SelectOrganizationContextRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $membershipId = (string) $request->validated('membership_id');

        try {
            $context = $this->contexts->forSelectedMembership(
                $principal->user,
                $membershipId,
            );
        } catch (InvalidOrganizationContext) {
            abort(404);
        }

        $this->selections->select(
            $request,
            $principal,
            (string) $context->membershipId,
        );

        return response()->json(
            $this->presenter->forUser(
                $principal->user,
                (string) $context->membershipId,
            ),
        )->header('Cache-Control', 'no-store');
    }
}
