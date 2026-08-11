<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Password\PasswordRecoveryFailed;
use App\Authentication\Password\PrivilegedPasswordRecoveryService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class PlatformIdentityRecoveryController extends Controller
{
    public function __construct(private readonly PrivilegedPasswordRecoveryService $recovery) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request, true);

        try {
            $result = $this->recovery->open(
                $this->actor($request),
                (string) $validated['target_user_id'],
                (string) $validated['reason_key'],
                (string) $validated['evidence_reference'],
                $request,
            );
        } catch (PasswordRecoveryFailed) {
            throw $this->rejected();
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function approve(Request $request, string $caseId): JsonResponse
    {
        $validated = $this->validateRequest($request, false);

        try {
            $result = $this->recovery->approve(
                $this->actor($request),
                $caseId,
                (string) $validated['evidence_reference'],
                $request,
            );
        } catch (PasswordRecoveryFailed) {
            throw $this->rejected();
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function complete(Request $request, string $caseId): JsonResponse
    {
        $validated = $this->validateRequest($request, false);

        try {
            $result = $this->recovery->complete(
                $this->actor($request),
                $caseId,
                (string) $validated['evidence_reference'],
                $request,
            );
        } catch (PasswordRecoveryFailed) {
            throw $this->rejected();
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function validateRequest(Request $request, bool $opening): array
    {
        $allowed = $opening
            ? ['target_user_id', 'reason_key', 'evidence_reference']
            : ['evidence_reference'];

        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw $this->rejected();
        }

        return $request->validate($opening ? [
            'target_user_id' => ['required', 'string', 'ulid'],
            'reason_key' => ['required', 'string', 'max:80'],
            'evidence_reference' => ['required', 'string', 'min:8', 'max:160'],
        ] : [
            'evidence_reference' => ['required', 'string', 'min:8', 'max:160'],
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return $actor;
    }

    private function rejected(): ValidationException
    {
        return ValidationException::withMessages([
            'recovery' => ['The identity recovery operation could not be completed.'],
        ]);
    }
}
