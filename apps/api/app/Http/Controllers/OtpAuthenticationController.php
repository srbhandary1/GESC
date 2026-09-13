<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Otp\OtpChallengeService;
use App\Authentication\Otp\OtpDeliveryUnavailable;
use App\Authentication\Otp\OtpVerificationFailed;
use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\PhoneNumberNormalizer;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Http\Problems\ProblemDetailsFactory;
use App\Identity\VerifiedIdentityProvisioner;
use App\Observability\CorrelationId;
use App\Tenancy\DirectLearnerProvisioner;
use App\Tenancy\Exceptions\IdempotencyKeyConflict;
use App\Tenancy\Exceptions\IdentityUnavailable;
use App\Tenancy\Exceptions\InstitutionInviteUnavailable;
use App\Tenancy\InstitutionInviteAcceptor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class OtpAuthenticationController extends Controller
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumbers,
        private readonly OtpChallengeService $challenges,
        private readonly DirectLearnerProvisioner $directLearners,
        private readonly VerifiedIdentityProvisioner $verifiedIdentities,
        private readonly InstitutionInviteAcceptor $institutionInvites,
        private readonly AuthenticationSessionService $sessions,
        private readonly ProblemDetailsFactory $problems,
        private readonly PasswordCredentialService $passwords,
    ) {}

    public function requestChallenge(Request $request): JsonResponse
    {
        $this->assertLegacyAuthenticationAvailable();
        $this->rejectUnexpectedFields($request, ['mobile_number']);
        $validated = $request->validate([
            'mobile_number' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ]);

        $mobileNumber = $this->normalize((string) $validated['mobile_number']);

        try {
            $challenge = $this->challenges->issue($mobileNumber);
        } catch (OtpDeliveryUnavailable) {
            return $this->problems->serviceUnavailable($request);
        }

        return response()->json([
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expiresAt->toIso8601String(),
        ], 202)->header('Cache-Control', 'no-store');
    }

    public function verifyChallenge(Request $request): JsonResponse
    {
        $this->assertLegacyAuthenticationAvailable();
        $this->rejectUnexpectedFields(
            $request,
            [
                'challenge_id',
                'mobile_number',
                'code',
                'institution_invite_code',
            ],
        );
        $validated = $request->validate([
            'challenge_id' => ['required', 'ulid'],
            'mobile_number' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'institution_invite_code' => [
                'sometimes',
                'string',
                'min:12',
                'max:128',
            ],
        ]);

        $mobileNumber = $this->normalize((string) $validated['mobile_number']);
        $challengeId = (string) $validated['challenge_id'];
        $institutionInviteCode = $validated['institution_invite_code'] ?? null;

        try {
            $result = $this->challenges->consumeAndRun(
                $challengeId,
                $mobileNumber,
                (string) $validated['code'],
                function () use (
                    $challengeId,
                    $institutionInviteCode,
                    $mobileNumber,
                    $request,
                ): array {
                    if (is_string($institutionInviteCode)) {
                        $provisioned = $this->verifiedIdentities->provision(
                            'phone',
                            $mobileNumber,
                        );
                        $this->institutionInvites->accept(
                            rawCode: $institutionInviteCode,
                            user: $provisioned->user,
                            correlationId: CorrelationId::forRequest($request),
                            idempotencyKey: $challengeId,
                        );
                    } else {
                        $provisioned = $this->directLearners
                            ->provisionVerifiedIdentity(
                                'phone',
                                $mobileNumber,
                            );
                    }
                    $authenticationSession = $this->sessions
                        ->start($provisioned->user, $request);

                    return [$provisioned->user, $authenticationSession];
                },
            );
        } catch (
            OtpVerificationFailed
            |IdempotencyKeyConflict
            |IdentityUnavailable
            |InstitutionInviteUnavailable
        ) {
            throw ValidationException::withMessages([
                'code' => ['The OTP challenge could not be verified.'],
            ]);
        } finally {
            $request->request->remove('code');
            $request->request->remove('mobile_number');
            $request->request->remove('institution_invite_code');

            if (is_string($institutionInviteCode)) {
                $institutionInviteCode = str_repeat(
                    '0',
                    strlen($institutionInviteCode),
                );
                unset($institutionInviteCode);
            }
        }

        [$user, $authenticationSession] = $result;

        return response()->json([
            'user' => [
                'id' => (string) $user->getAuthIdentifier(),
            ],
            'session' => [
                'id' => (string) $authenticationSession->getKey(),
                'current' => true,
                'created_at' => $authenticationSession->started_at->toIso8601String(),
                'last_activity_at' => $authenticationSession->last_seen_at->toIso8601String(),
                'expires_at' => $authenticationSession->idle_expires_at
                    ->min($authenticationSession->absolute_expires_at)
                    ->toIso8601String(),
                'privileged' => $authenticationSession->is_privileged,
                'mfa_verified_at' => $authenticationSession->mfa_verified_at?->toIso8601String(),
                'mfa_verified_method' => $authenticationSession->mfa_verified_method,
                'mfa_verified_scope' => $authenticationSession->mfa_verified_scope,
                'password_confirmed_at' => null,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function normalize(string $mobileNumber): string
    {
        try {
            return $this->phoneNumbers->normalize($mobileNumber);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'mobile_number' => ['The mobile number must use E.164 format.'],
            ]);
        }
    }

    private function assertLegacyAuthenticationAvailable(): void
    {
        if ($this->passwords->mode() === 'password_only') {
            abort(Response::HTTP_GONE, 'The legacy authentication contract is retired.');
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unexpected request fields are not permitted.'],
            ]);
        }
    }
}
