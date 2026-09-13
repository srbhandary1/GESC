<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Password\AccountRecoveryManagementService;
use App\Authentication\Password\PasswordRecoveryFailed;
use App\Http\Middleware\EnforceAuthenticationSession;
use App\Models\AuthenticationSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class AccountRecoveryManagementController extends Controller
{
    public function __construct(
        private readonly AccountRecoveryManagementService $recovery,
    ) {}

    public function requestSetup(Request $request): JsonResponse
    {
        $this->rejectUnexpectedFields(
            $request,
            ['target_user_id', 'reason_key', 'evidence_reference'],
        );
        $validated = $request->validate([
            'target_user_id' => ['required', 'string', 'ulid'],
            'reason_key' => ['required', 'string', 'max:80'],
            'evidence_reference' => ['required', 'string', 'min:8', 'max:160'],
        ]);
        [$actor, $session] = $this->context($request);

        try {
            $result = $this->recovery->requestCredentialSetup(
                $actor,
                $session,
                (string) $validated['target_user_id'],
                (string) $validated['reason_key'],
                (string) $validated['evidence_reference'],
                $request,
            );
        } catch (PasswordRecoveryFailed|InvalidArgumentException) {
            throw $this->rejected();
        } finally {
            $request->request->remove('evidence_reference');
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function replaceContact(Request $request, string $userId): JsonResponse
    {
        $this->rejectUnexpectedFields(
            $request,
            ['contact_type', 'contact', 'reason_key', 'evidence_reference'],
        );
        $validated = $request->validate([
            'contact_type' => ['required', 'string', 'in:email,phone'],
            'contact' => ['required', 'string', 'max:254'],
            'reason_key' => ['required', 'string', 'max:80'],
            'evidence_reference' => ['required', 'string', 'min:8', 'max:160'],
        ]);
        [$actor, $session] = $this->context($request);

        try {
            $result = $this->recovery->replaceRecoveryContact(
                $actor,
                $session,
                $userId,
                (string) $validated['contact_type'],
                (string) $validated['contact'],
                (string) $validated['reason_key'],
                (string) $validated['evidence_reference'],
                $request,
            );
        } catch (PasswordRecoveryFailed|InvalidArgumentException) {
            throw $this->rejected();
        } finally {
            $request->request->remove('contact');
            $request->request->remove('evidence_reference');
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return array{User, AuthenticationSession} */
    private function context(Request $request): array
    {
        $actor = $request->user('web');
        $session = $request->attributes->get(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
        );
        abort_unless(
            $actor instanceof User && $session instanceof AuthenticationSession,
            Response::HTTP_UNAUTHORIZED,
        );

        return [$actor, $session];
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw $this->rejected();
        }
    }

    private function rejected(): ValidationException
    {
        return ValidationException::withMessages([
            'recovery' => ['The account recovery operation could not be completed.'],
        ]);
    }
}
