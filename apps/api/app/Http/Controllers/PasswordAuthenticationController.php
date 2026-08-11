<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Password\FreshPasswordConfirmationRequired;
use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Authentication\Password\PasswordRecoveryFailed;
use App\Authentication\Password\PasswordRecoveryService;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Http\Middleware\EnforceAuthenticationSession;
use App\Models\AuthenticationSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class PasswordAuthenticationController extends Controller
{
    public function __construct(
        private readonly PasswordCredentialService $passwords,
        private readonly AuthenticationSessionService $sessions,
        private readonly MobileTokenFamilyService $mobileFamilies,
        private readonly PasswordRecoveryService $recovery,
    ) {}

    public function signIn(Request $request): JsonResponse
    {
        if ($this->passwords->mode() === 'legacy_otp') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->rejectUnexpectedFields($request, ['username', 'password']);
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:512'],
        ]);

        try {
            $user = $this->passwords->authenticate(
                (string) $validated['username'],
                (string) $validated['password'],
                $request,
            );
            $session = $this->sessions->start($user, $request);
        } catch (PasswordAuthenticationFailed) {
            throw ValidationException::withMessages([
                'credentials' => ['Sign-in could not be completed.'],
            ]);
        } finally {
            $request->request->remove('password');
        }

        return response()->json([
            'user' => ['id' => (string) $user->getKey()],
            'session' => $this->sessionPayload($session),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function mode(): JsonResponse
    {
        return response()->json(['mode' => $this->passwords->mode()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request): JsonResponse
    {
        [$user, $session] = $this->context($request);

        return response()->json([
            'username' => $user->username,
            'credential_ready' => $user->passwordCredential()->exists(),
            'mode' => $this->passwords->mode(),
            'password_confirmed_at' => $session->password_confirmed_at?->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function requestRecovery(Request $request): JsonResponse
    {
        if ($this->passwords->mode() === 'legacy_otp') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->rejectUnexpectedFields($request, ['username']);
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
        ]);
        $this->recovery->request((string) $validated['username']);

        return response()->json([
            'message' => 'If the account is eligible, recovery instructions will be sent.',
        ], Response::HTTP_ACCEPTED)->header('Cache-Control', 'private, no-store');
    }

    public function recover(Request $request): Response
    {
        if ($this->passwords->mode() === 'legacy_otp') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->rejectUnexpectedFields(
            $request,
            ['challenge_id', 'username', 'token', 'new_password'],
        );
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'ulid'],
            'username' => ['required', 'string', 'max:64'],
            'token' => ['required', 'string', 'min:32', 'max:256'],
            'new_password' => ['required', 'string', 'max:512'],
        ]);

        try {
            $completed = DB::transaction(function () use ($request, $validated): bool {
                try {
                    $user = $this->recovery->consume(
                        (string) $validated['challenge_id'],
                        (string) $validated['username'],
                        (string) $validated['token'],
                        (string) $validated['new_password'],
                        $request,
                    );
                } catch (PasswordRecoveryFailed|PasswordPolicyViolation) {
                    return false;
                }
                $this->mobileFamilies->revokeAllForUser(
                    (string) $user->getKey(),
                    'password_recovered',
                );
                $this->sessions->revokeAll($user, $request, 'password_recovered');

                return true;
            }, 3);

            if (! $completed) {
                throw new PasswordRecoveryFailed;
            }
        } catch (PasswordRecoveryFailed) {
            throw ValidationException::withMessages([
                'recovery' => ['Password recovery could not be completed.'],
            ]);
        } finally {
            $request->request->remove('token');
            $request->request->remove('new_password');
        }

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    public function establishRecovered(Request $request): Response
    {
        $this->rejectUnexpectedFields(
            $request,
            ['challenge_id', 'username', 'token', 'password'],
        );
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'ulid'],
            'username' => ['required', 'string', 'max:64'],
            'token' => ['required', 'string', 'min:32', 'max:256'],
            'password' => ['required', 'string', 'max:512'],
        ]);

        try {
            $completed = DB::transaction(function () use ($request, $validated): bool {
                try {
                    $user = $this->recovery->consumeExcludedEstablishment(
                        (string) $validated['challenge_id'],
                        (string) $validated['username'],
                        (string) $validated['token'],
                        (string) $validated['password'],
                        $request,
                    );
                } catch (PasswordRecoveryFailed|PasswordPolicyViolation) {
                    return false;
                }
                $this->mobileFamilies->revokeAllForUser(
                    (string) $user->getKey(),
                    'excluded_password_established',
                );
                $this->sessions->revokeAll(
                    $user,
                    $request,
                    'excluded_password_established',
                );

                return true;
            }, 3);

            if (! $completed) {
                throw new PasswordRecoveryFailed;
            }
        } catch (PasswordRecoveryFailed) {
            throw ValidationException::withMessages([
                'recovery' => ['Credential establishment could not be completed.'],
            ]);
        } finally {
            $request->request->remove('token');
            $request->request->remove('password');
        }

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    public function establish(Request $request): Response
    {
        if ($this->passwords->mode() !== 'credential_migration') {
            abort(Response::HTTP_CONFLICT);
        }

        $this->rejectUnexpectedFields($request, ['username', 'password']);
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:512'],
        ]);
        [$user] = $this->context($request);

        try {
            DB::transaction(function () use ($request, $user, $validated): void {
                $this->passwords->establish(
                    $user,
                    (string) $validated['username'],
                    (string) $validated['password'],
                    $request,
                );
                $this->mobileFamilies->revokeAllForUser((string) $user->getKey(), 'password_established');
                $this->sessions->revokeAll($user, $request, 'password_established');
            }, 3);
        } catch (PasswordPolicyViolation|PasswordAuthenticationFailed) {
            throw ValidationException::withMessages([
                'credentials' => ['The username or password could not be established.'],
            ]);
        } finally {
            $request->request->remove('password');
        }

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request): JsonResponse
    {
        $this->rejectUnexpectedFields($request, ['password']);
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:512'],
        ]);
        [$user, $session] = $this->context($request);

        try {
            $this->passwords->confirm($user, $session, (string) $validated['password'], $request);
        } catch (PasswordAuthenticationFailed) {
            throw ValidationException::withMessages([
                'credentials' => ['Password confirmation could not be completed.'],
            ]);
        } finally {
            $request->request->remove('password');
        }

        return response()->json([
            'confirmed_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes((int) config(
                'authentication.password.confirmation_minutes',
                5,
            ))->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function changePassword(Request $request): Response
    {
        $this->rejectUnexpectedFields($request, ['current_password', 'new_password']);
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:512'],
            'new_password' => ['required', 'string', 'max:512'],
        ]);
        [$user] = $this->context($request);

        try {
            DB::transaction(function () use ($request, $user, $validated): void {
                $this->passwords->changePassword(
                    $user,
                    (string) $validated['current_password'],
                    (string) $validated['new_password'],
                    $request,
                );
                $this->mobileFamilies->revokeAllForUser((string) $user->getKey(), 'password_changed');
                $this->sessions->revokeAll($user, $request, 'password_changed');
            }, 3);
        } catch (PasswordAuthenticationFailed|PasswordPolicyViolation) {
            throw ValidationException::withMessages([
                'credentials' => ['The password could not be changed.'],
            ]);
        } finally {
            $request->request->remove('current_password');
            $request->request->remove('new_password');
        }

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    public function changeUsername(Request $request): Response
    {
        $this->rejectUnexpectedFields($request, ['username']);
        $validated = $request->validate(['username' => ['required', 'string', 'max:64']]);
        [$user, $session] = $this->context($request);

        try {
            DB::transaction(function () use ($request, $session, $user, $validated): void {
                $this->passwords->changeUsername($user, $session, (string) $validated['username'], $request);
                $this->mobileFamilies->revokeAllForUser((string) $user->getKey(), 'username_changed');
                $this->sessions->revokeAll($user, $request, 'username_changed');
            }, 3);
        } catch (PasswordPolicyViolation) {
            throw ValidationException::withMessages([
                'username' => ['The username is unavailable.'],
            ]);
        } catch (FreshPasswordConfirmationRequired) {
            throw new FreshPasswordConfirmationRequired;
        }

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    /** @return array{User, AuthenticationSession} */
    private function context(Request $request): array
    {
        $user = $request->user('web');
        $session = $request->attributes->get(EnforceAuthenticationSession::REQUEST_ATTRIBUTE);
        abort_unless($user instanceof User && $session instanceof AuthenticationSession, 401);

        return [$user, $session];
    }

    /** @return array<string, mixed> */
    private function sessionPayload(AuthenticationSession $session): array
    {
        return [
            'id' => (string) $session->getKey(),
            'current' => true,
            'created_at' => $session->started_at->toIso8601String(),
            'last_activity_at' => $session->last_seen_at->toIso8601String(),
            'expires_at' => $session->idle_expires_at->min($session->absolute_expires_at)->toIso8601String(),
            'privileged' => $session->is_privileged,
            'mfa_verified_at' => null,
            'mfa_verified_method' => null,
            'mfa_verified_scope' => null,
            'password_confirmed_at' => null,
        ];
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unexpected request fields are not permitted.'],
            ]);
        }
    }
}
