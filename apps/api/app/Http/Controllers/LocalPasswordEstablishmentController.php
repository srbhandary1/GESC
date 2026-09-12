<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Authentication\Password\PasswordRecoveryFailed;
use App\Authentication\Password\PasswordRecoveryService;
use App\Authentication\Password\ProtectedLocalPasswordRecoveryDeliveryAdapter;
use App\Authentication\Sessions\AuthenticationSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class LocalPasswordEstablishmentController extends Controller
{
    public function __construct(
        private readonly ProtectedLocalPasswordRecoveryDeliveryAdapter $delivery,
        private readonly PasswordRecoveryService $recovery,
        private readonly AuthenticationSessionService $sessions,
        private readonly MobileTokenFamilyService $mobileFamilies,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $this->guardLocal($request);

        return response()->json([
            'delivery' => $this->delivery->latestEstablishmentMetadata(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function establish(Request $request): Response
    {
        $this->guardLocal($request);
        $this->rejectUnexpectedFields($request, ['challenge_id', 'username', 'password']);
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'ulid'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:512'],
        ]);
        $challengeId = (string) $validated['challenge_id'];

        try {
            $completed = DB::transaction(function () use ($challengeId, $request, $validated): bool {
                try {
                    $user = $this->recovery->consumeAccountSetup(
                        $challengeId,
                        (string) $validated['username'],
                        $this->delivery->tokenFor($challengeId),
                        (string) $validated['password'],
                        $request,
                    );
                } catch (PasswordRecoveryFailed|PasswordPolicyViolation) {
                    return false;
                }
                $this->mobileFamilies->revokeAllForUser(
                    (string) $user->getKey(),
                    'account_setup_completed',
                );
                $this->sessions->revokeAll(
                    $user,
                    $request,
                    'account_setup_completed',
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
            $request->request->remove('password');
        }

        $this->delivery->forget($challengeId);

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    private function guardLocal(Request $request): void
    {
        abort_unless(
            app()->environment(['local', 'testing'])
                && config('authentication.password.recovery_delivery_driver') === 'protected-local'
                && in_array($request->ip(), ['127.0.0.1', '::1'], true),
            Response::HTTP_NOT_FOUND,
        );
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
