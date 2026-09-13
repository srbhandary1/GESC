<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Mobile\OAuthAuthorizationTarget;
use App\Authentication\Otp\OtpChallengeService;
use App\Authentication\Otp\OtpDeliveryUnavailable;
use App\Authentication\Otp\OtpVerificationFailed;
use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\PhoneNumberNormalizer;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Identity\VerifiedIdentityProvisioner;
use App\Observability\CorrelationId;
use App\Tenancy\DirectLearnerProvisioner;
use App\Tenancy\Exceptions\IdempotencyKeyConflict;
use App\Tenancy\Exceptions\IdentityUnavailable;
use App\Tenancy\Exceptions\InstitutionInviteUnavailable;
use App\Tenancy\InstitutionInviteAcceptor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

final class MobileAuthorizationLoginController extends Controller
{
    private const CHALLENGE_KEY = 'mobile_oauth.challenge_id';

    private const MOBILE_CIPHERTEXT_KEY = 'mobile_oauth.mobile_ciphertext';

    private const INVITE_CIPHERTEXT_KEY = 'mobile_oauth.invite_ciphertext';

    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumbers,
        private readonly OtpChallengeService $challenges,
        private readonly DirectLearnerProvisioner $directLearners,
        private readonly VerifiedIdentityProvisioner $verifiedIdentities,
        private readonly InstitutionInviteAcceptor $institutionInvites,
        private readonly AuthenticationSessionService $sessions,
        private readonly OAuthAuthorizationTarget $targets,
        private readonly PasswordCredentialService $passwords,
    ) {}

    public function show(Request $request): Response
    {
        if ($this->targets->current($request) === null) {
            return response()->view('auth.mobile-sign-in', [
                'error' => 'Start sign-in from the installed Global English application.',
            ], 400);
        }

        return response()->view('auth.mobile-sign-in', [
            'passwordMode' => $this->passwords->mode() !== 'legacy_otp',
        ]);
    }

    public function submitPassword(Request $request): Response|RedirectResponse
    {
        $target = $this->targets->current($request);

        if (! is_string($target) || $this->passwords->mode() === 'legacy_otp') {
            return response()->view('auth.mobile-sign-in', [
                'passwordMode' => true,
                'error' => 'The sign-in attempt has expired. Please start again.',
            ], 422);
        }

        $password = (string) $request->input('password', '');

        try {
            $user = $this->passwords->authenticate(
                (string) $request->input('username', ''),
                $password,
                $request,
            );
            $this->sessions->start($user, $request);
        } catch (PasswordAuthenticationFailed) {
            return response()->view('auth.mobile-sign-in', [
                'passwordMode' => true,
                'error' => 'Sign-in could not be completed. Check the details and try again.',
            ], 422);
        } finally {
            $request->request->remove('password');
            $password = str_repeat('0', strlen($password));
            unset($password);
        }

        $this->targets->forget($request);

        return redirect($target);
    }

    public function requestChallenge(Request $request): Response
    {
        if ($this->passwords->mode() === 'password_only') {
            return response('The legacy authentication contract is retired.', 410);
        }

        try {
            $mobileNumber = $this->phoneNumbers->normalize(
                (string) $request->input('mobile_number', ''),
            );
        } catch (InvalidArgumentException) {
            return response()->view('auth.mobile-sign-in', [
                'error' => 'Enter a valid mobile number with its country code.',
            ], 422);
        }

        try {
            $institutionInviteCode = $this->institutionInviteCode($request);
        } catch (InvalidArgumentException) {
            return response()->view('auth.mobile-sign-in', [
                'error' => 'Enter a valid institution invite code or leave it blank.',
            ], 422);
        }

        $request->request->remove('mobile_number');
        $request->request->remove('institution_invite_code');

        try {
            $challenge = $this->challenges->issue($mobileNumber);
        } catch (OtpDeliveryUnavailable) {
            return response()->view('auth.mobile-sign-in', [
                'error' => 'Sign-in is temporarily unavailable. Please try again.',
            ], 503);
        }

        $request->session()->put([
            self::CHALLENGE_KEY => $challenge->id,
            self::MOBILE_CIPHERTEXT_KEY => Crypt::encryptString($mobileNumber),
        ]);
        $request->session()->forget(self::INVITE_CIPHERTEXT_KEY);

        if ($institutionInviteCode !== null) {
            $request->session()->put(
                self::INVITE_CIPHERTEXT_KEY,
                Crypt::encryptString($institutionInviteCode),
            );
            $institutionInviteCode = str_repeat(
                '0',
                strlen($institutionInviteCode),
            );
            unset($institutionInviteCode);
        }

        return response()->view('auth.mobile-verify');
    }

    public function verifyChallenge(Request $request): Response|RedirectResponse
    {
        if ($this->passwords->mode() === 'password_only') {
            return response('The legacy authentication contract is retired.', 410);
        }

        $code = (string) $request->input('code', '');

        if (preg_match('/^[0-9]{6}$/', $code) !== 1) {
            return response()->view('auth.mobile-verify', [
                'error' => 'The verification code could not be verified.',
            ], 422);
        }

        $challengeId = $request->session()->get(self::CHALLENGE_KEY);
        $mobileCiphertext = $request->session()->get(self::MOBILE_CIPHERTEXT_KEY);
        $inviteCiphertext = $request->session()->get(self::INVITE_CIPHERTEXT_KEY);
        $target = $this->targets->current($request);

        if (
            ! is_string($challengeId)
            || ! is_string($mobileCiphertext)
            || ($inviteCiphertext !== null && ! is_string($inviteCiphertext))
            || ! is_string($target)
        ) {
            return response()->view('auth.mobile-sign-in', [
                'error' => 'The sign-in attempt has expired. Please start again.',
            ], 422);
        }

        try {
            $mobileNumber = Crypt::decryptString($mobileCiphertext);
            $institutionInviteCode = is_string($inviteCiphertext)
                ? Crypt::decryptString($inviteCiphertext)
                : null;
        } catch (DecryptException) {
            $this->forgetAttemptSecrets($request);

            return response()->view('auth.mobile-sign-in', [
                'error' => 'The sign-in attempt has expired. Please start again.',
            ], 422);
        }

        $request->request->remove('code');

        try {
            $this->challenges->consumeAndRun(
                $challengeId,
                $mobileNumber,
                $code,
                function () use (
                    $challengeId,
                    $institutionInviteCode,
                    $mobileNumber,
                    $request,
                ): void {
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
                    $this->sessions->start($provisioned->user, $request);
                },
            );
        } catch (
            OtpVerificationFailed
            |IdempotencyKeyConflict
            |IdentityUnavailable
            |InstitutionInviteUnavailable
        ) {
            return response()->view('auth.mobile-verify', [
                'error' => 'The verification code could not be verified.',
            ], 422);
        } finally {
            $code = str_repeat('0', strlen($code));
            unset($code);

            if (is_string($institutionInviteCode)) {
                $institutionInviteCode = str_repeat(
                    '0',
                    strlen($institutionInviteCode),
                );
                unset($institutionInviteCode);
            }
        }

        $this->forgetAttemptSecrets($request);
        $this->targets->forget($request);

        return redirect($target);
    }

    private function institutionInviteCode(Request $request): ?string
    {
        $candidate = $request->input('institution_invite_code');

        if ($candidate === null || $candidate === '') {
            return null;
        }

        if (
            ! is_string($candidate)
            || strlen($candidate) < 12
            || strlen($candidate) > 128
        ) {
            throw new InvalidArgumentException;
        }

        return $candidate;
    }

    private function forgetAttemptSecrets(Request $request): void
    {
        $request->session()->forget([
            self::CHALLENGE_KEY,
            self::MOBILE_CIPHERTEXT_KEY,
            self::INVITE_CIPHERTEXT_KEY,
        ]);
    }
}
