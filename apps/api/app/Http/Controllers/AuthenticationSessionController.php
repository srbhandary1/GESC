<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\AuthenticationSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationSessionController extends Controller
{
    public function __construct(private readonly AuthenticationSessionService $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $currentId = (string) $request->session()->get(
            AuthenticationSessionService::SESSION_KEY,
            '',
        );

        $sessions = $this->sessions->activeFor($user)->map(
            static fn (AuthenticationSession $authenticationSession): array => [
                'id' => (string) $authenticationSession->getKey(),
                'current' => hash_equals(
                    $currentId,
                    (string) $authenticationSession->getKey(),
                ),
                'created_at' => $authenticationSession->started_at->toIso8601String(),
                'last_activity_at' => $authenticationSession->last_seen_at->toIso8601String(),
                'expires_at' => $authenticationSession->idle_expires_at
                    ->min($authenticationSession->absolute_expires_at)
                    ->toIso8601String(),
                'privileged' => $authenticationSession->is_privileged,
                'mfa_verified_at' => $authenticationSession->mfa_verified_at?->toIso8601String(),
                'mfa_verified_method' => $authenticationSession->mfa_verified_method,
                'mfa_verified_scope' => $authenticationSession->mfa_verified_scope,
                'password_confirmed_at' => $authenticationSession
                    ->password_confirmed_at?->toIso8601String(),
            ],
        )->values();

        return response()->json(['data' => $sessions])
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, string $sessionId): Response
    {
        $this->sessions->revoke(
            $this->authenticatedUser($request),
            $sessionId,
            $request,
        );

        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    public function destroyAll(Request $request): Response
    {
        $this->sessions->revokeAll($this->authenticatedUser($request), $request);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): Response
    {
        $this->sessions->revokeCurrent(
            $this->authenticatedUser($request),
            $request,
        );

        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    private function authenticatedUser(Request $request): Authenticatable
    {
        $user = $request->user('web');

        abort_unless($user instanceof Authenticatable, 401);

        return $user;
    }
}
