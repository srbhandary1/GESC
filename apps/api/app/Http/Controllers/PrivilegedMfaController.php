<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Mfa\PrivilegedMfaOperationRejected;
use App\Authentication\Mfa\PrivilegedMfaService;
use App\Authentication\Mfa\PrivilegedMfaVerificationFailed;
use App\Authentication\Password\PasswordCredentialService;
use App\Http\Middleware\EnforceAuthenticationSession;
use App\Models\AuthenticationSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class PrivilegedMfaController extends Controller
{
    public function __construct(
        private readonly PrivilegedMfaService $mfa,
        private readonly PasswordCredentialService $passwords,
    ) {}

    public function show(Request $request): JsonResponse
    {
        [$user, $session] = $this->authenticationContext($request);

        return response()->json($this->mfa->status($user, $session))
            ->header('Cache-Control', 'private, no-store');
    }

    public function beginEnrollment(Request $request): JsonResponse
    {
        $this->rejectUnexpectedFields($request, []);
        [$user, $session] = $this->authenticationContext($request);

        try {
            $result = $this->mfa->beginEnrollment(
                $user,
                $session,
                $request,
            );
        } catch (PrivilegedMfaOperationRejected) {
            abort(Response::HTTP_CONFLICT);
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function confirmEnrollment(
        Request $request,
        string $factorId,
    ): JsonResponse {
        $this->rejectUnexpectedFields($request, ['challenge_id', 'code']);
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'ulid'],
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ]);
        [$user, $session] = $this->authenticationContext($request);

        try {
            $result = $this->mfa->confirmEnrollment(
                $user,
                $session,
                $factorId,
                (string) $validated['challenge_id'],
                (string) $validated['code'],
                $request,
            );
        } catch (PrivilegedMfaVerificationFailed) {
            throw ValidationException::withMessages([
                'assertion' => ['The MFA assertion could not be verified.'],
            ]);
        } finally {
            $request->request->remove('code');
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function issueChallenge(Request $request): JsonResponse
    {
        $this->rejectUnexpectedFields($request, []);
        [$user, $session] = $this->authenticationContext($request);

        try {
            $result = $this->mfa->issueAssertionChallenge(
                $user,
                $session,
                $request,
            );
        } catch (PrivilegedMfaOperationRejected) {
            abort(Response::HTTP_CONFLICT);
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function verifyChallenge(
        Request $request,
        string $challengeId,
    ): JsonResponse {
        $this->rejectUnexpectedFields($request, ['method', 'code']);
        $validated = $request->validate([
            'method' => [
                'required',
                'string',
                Rule::in([
                    AuthenticationSession::MFA_METHOD_TOTP,
                    AuthenticationSession::MFA_METHOD_RECOVERY_CODE,
                ]),
            ],
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ]);
        [$user, $session] = $this->authenticationContext($request);

        try {
            $result = $this->mfa->verifyAssertionChallenge(
                $user,
                $session,
                $challengeId,
                (string) $validated['method'],
                (string) $validated['code'],
                $request,
            );
        } catch (PrivilegedMfaVerificationFailed) {
            throw ValidationException::withMessages([
                'assertion' => ['The MFA assertion could not be verified.'],
            ]);
        } finally {
            $request->request->remove('code');
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $this->rejectUnexpectedFields($request, []);
        [$user, $session] = $this->authenticationContext($request);

        try {
            $result = $this->mfa->regenerateRecoveryCodes(
                $user,
                $session,
                $request,
            );
        } catch (PrivilegedMfaOperationRejected) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function revokeFactor(Request $request): Response
    {
        $this->rejectUnexpectedFields($request, []);
        [$user, $session] = $this->authenticationContext($request);

        try {
            $this->mfa->revokeFactor($user, $session, $request);
        } catch (PrivilegedMfaOperationRejected) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return response()->noContent()
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array{User, AuthenticationSession}
     */
    private function authenticationContext(Request $request): array
    {
        if ($this->passwords->mode() === 'password_only') {
            abort(Response::HTTP_GONE, 'The legacy authentication contract is retired.');
        }

        $user = $request->user('web');
        $session = $request->attributes->get(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
        );

        abort_unless(
            $user instanceof User && $session instanceof AuthenticationSession,
            Response::HTTP_UNAUTHORIZED,
        );

        return [$user, $session];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function rejectUnexpectedFields(
        Request $request,
        array $allowed,
    ): void {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unexpected request fields are not permitted.'],
            ]);
        }
    }
}
