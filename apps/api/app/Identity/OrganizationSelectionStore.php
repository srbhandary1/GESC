<?php

declare(strict_types=1);

namespace App\Identity;

use App\Authentication\AuthenticatedPrincipal;
use App\Authentication\AuthenticationTransport;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

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
            return $this->selectedWeb($request, $principal);
        }

        return $principal->mobileTokenFamily?->selected_membership_id;
    }

    public function select(
        Request $request,
        AuthenticatedPrincipal $principal,
        string $membershipId,
    ): void {
        if ($principal->transport === AuthenticationTransport::WebSession) {
            $this->selectWeb($request, $principal, $membershipId);

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
            $this->clearWeb($request, $principal);

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

    private function selectedWeb(
        Request $request,
        AuthenticatedPrincipal $principal,
    ): ?string {
        $storedMembershipId = $request->session()->get(self::WEB_SESSION_KEY);
        $storedMembershipId = is_string($storedMembershipId)
            && $storedMembershipId !== ''
                ? $storedMembershipId
                : null;
        $authoritativeMembershipId = $principal->webSession
            ?->selected_membership_id;

        if ($storedMembershipId === $authoritativeMembershipId) {
            return $authoritativeMembershipId;
        }

        $this->clearWeb($request, $principal);

        throw new AuthenticationException;
    }

    private function selectWeb(
        Request $request,
        AuthenticatedPrincipal $principal,
        string $membershipId,
    ): void {
        $webSession = $principal->webSession;

        if (! $webSession instanceof AuthenticationSession) {
            throw new AuthenticationException;
        }

        $organizationId = DB::transaction(function () use (
            $principal,
            $webSession,
            $membershipId,
        ): string {
            $membership = DB::table('memberships')
                ->where('id', $membershipId)
                ->where(
                    'user_id',
                    (string) $principal->user->getAuthIdentifier(),
                )
                ->where('status', Membership::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first(['organization_id']);

            if (! $membership instanceof stdClass) {
                throw new AuthenticationException;
            }

            $organizationId = $membership->organization_id;

            if (
                ! is_string($organizationId)
                || ! DB::table('organizations')
                    ->where('id', $organizationId)
                    ->where('status', Organization::STATUS_ACTIVE)
                    ->exists()
            ) {
                throw new AuthenticationException;
            }

            $updated = DB::table('authentication_sessions')
                ->where('id', (string) $webSession->getKey())
                ->where(
                    'user_id',
                    (string) $principal->user->getAuthIdentifier(),
                )
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->update([
                    'selected_organization_id' => $organizationId,
                    'selected_membership_id' => $membershipId,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new AuthenticationException;
            }

            return $organizationId;
        });

        $request->session()->put(self::WEB_SESSION_KEY, $membershipId);
        $webSession->forceFill([
            'selected_organization_id' => $organizationId,
            'selected_membership_id' => $membershipId,
        ]);
    }

    private function clearWeb(
        Request $request,
        AuthenticatedPrincipal $principal,
    ): void {
        $webSession = $principal->webSession;

        if ($webSession instanceof AuthenticationSession) {
            DB::table('authentication_sessions')
                ->where('id', (string) $webSession->getKey())
                ->where(
                    'user_id',
                    (string) $principal->user->getAuthIdentifier(),
                )
                ->update([
                    'selected_organization_id' => null,
                    'selected_membership_id' => null,
                    'updated_at' => now(),
                ]);
            $webSession->forceFill([
                'selected_organization_id' => null,
                'selected_membership_id' => null,
            ]);
        }

        $request->session()->forget(self::WEB_SESSION_KEY);
    }
}
