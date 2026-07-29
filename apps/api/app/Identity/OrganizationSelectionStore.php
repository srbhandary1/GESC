<?php

declare(strict_types=1);

namespace App\Identity;

use App\Authentication\AuthenticatedPrincipal;
use App\Authentication\AuthenticationTransport;
use App\Authentication\Mobile\MobileTokenFamilyService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final readonly class OrganizationSelectionStore
{
    public const string WEB_SESSION_KEY = 'selected_organization_membership_id';

    public function __construct(
        private MobileTokenFamilyService $mobileFamilies,
    ) {}

    public function selected(
        Request $request,
        AuthenticatedPrincipal $principal,
    ): ?string {
        if ($principal->transport === AuthenticationTransport::WebSession) {
            $membershipId = $request->session()->get(self::WEB_SESSION_KEY);

            return is_string($membershipId) && $membershipId !== ''
                ? $membershipId
                : null;
        }

        return $principal->mobileTokenFamily?->selected_membership_id;
    }

    public function select(
        Request $request,
        AuthenticatedPrincipal $principal,
        string $membershipId,
    ): void {
        if ($principal->transport === AuthenticationTransport::WebSession) {
            $request->session()->put(self::WEB_SESSION_KEY, $membershipId);

            return;
        }

        $family = $principal->mobileTokenFamily;

        if ($family === null) {
            throw new AuthenticationException;
        }

        $selected = $this->mobileFamilies->selectMembership(
            (string) $family->getKey(),
            (string) $principal->user->getKey(),
            $membershipId,
        );

        if ($selected === null) {
            throw new AuthenticationException;
        }
    }

    public function clear(
        Request $request,
        AuthenticatedPrincipal $principal,
    ): void {
        if ($principal->transport === AuthenticationTransport::WebSession) {
            $request->session()->forget(self::WEB_SESSION_KEY);

            return;
        }

        $family = $principal->mobileTokenFamily;

        if ($family !== null) {
            $this->mobileFamilies->clearSelectedMembership(
                (string) $family->getKey(),
                (string) $principal->user->getKey(),
            );
        }
    }
}
