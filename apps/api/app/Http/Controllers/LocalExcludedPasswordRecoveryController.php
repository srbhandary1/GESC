<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Password\PasswordRecoveryFailed;
use App\Authentication\Password\PrivilegedPasswordRecoveryService;
use App\Authentication\Sessions\PrivilegeClassifier;
use App\Models\PasswordRecoveryChallenge;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class LocalExcludedPasswordRecoveryController extends Controller
{
    private const string EVIDENCE_REFERENCE = 'dr-0027:local-excluded-operator';

    public function __construct(
        private readonly PrivilegedPasswordRecoveryService $recovery,
        private readonly PrivilegeClassifier $privileges,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $target = $this->target();
        $this->assertConfiguredActor($actor);

        return response()->json([
            'target' => [
                'membership_type' => 'organization_admin',
                'recovery_hint' => $this->recoveryHint($target),
            ],
            'case' => $this->currentCase($target),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function open(Request $request): JsonResponse
    {
        $this->rejectBody($request);
        $actor = $this->actor($request);
        $target = $this->target();

        try {
            $result = $this->recovery->open(
                $actor,
                (string) $target->getKey(),
                'identity_recovery.verified_support_case',
                self::EVIDENCE_REFERENCE,
                $request,
            );
        } catch (PasswordRecoveryFailed) {
            throw $this->rejected();
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function approve(Request $request): JsonResponse
    {
        $this->rejectBody($request);
        $target = $this->target();
        $case = $this->currentCaseRow($target);

        try {
            $result = $this->recovery->approve(
                $this->actor($request),
                (string) $case->id,
                self::EVIDENCE_REFERENCE,
                $request,
            );
        } catch (PasswordRecoveryFailed) {
            throw $this->rejected();
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function complete(Request $request): JsonResponse
    {
        $this->rejectBody($request);
        $target = $this->target();
        $case = $this->currentCaseRow($target);

        try {
            $result = $this->recovery->complete(
                $this->actor($request),
                (string) $case->id,
                self::EVIDENCE_REFERENCE,
                $request,
            );
        } catch (PasswordRecoveryFailed) {
            throw $this->rejected();
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    private function actor(Request $request): User
    {
        $this->guardLocal($request);
        $actor = $request->user('web');
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return $actor;
    }

    private function assertConfiguredActor(User $actor): void
    {
        $configured = config('platform.identity_recovery.actor_user_ids', []);
        abort_unless(
            is_array($configured)
                && in_array((string) $actor->getKey(), $configured, true),
            Response::HTTP_FORBIDDEN,
        );
    }

    private function target(): User
    {
        $targetIds = DB::table('password_cutover_exclusions')
            ->join('users', 'users.id', '=', 'password_cutover_exclusions.user_id')
            ->leftJoin('password_credentials', 'password_credentials.user_id', '=', 'users.id')
            ->where('users.status', User::STATUS_ACTIVE)
            ->whereNull('users.username')
            ->whereNull('password_credentials.user_id')
            ->pluck('users.id');
        $targets = User::query()->whereIn('id', $targetIds)->get()
            ->filter(fn (User $user): bool => $this->privileges->isPrivileged($user))
            ->values();
        abort_unless($targets->count() === 1, Response::HTTP_CONFLICT);

        return $targets->firstOrFail();
    }

    /** @return array<string, mixed>|null */
    private function currentCase(User $target): ?array
    {
        $case = DB::table('privileged_password_recovery_cases')
            ->where('target_user_id', $target->getKey())
            ->where('recovery_kind', PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT)
            ->whereIn('status', ['pending_approval', 'waiting_delay', 'completed'])
            ->latest('created_at')->first();

        if (! $case instanceof \stdClass) {
            return null;
        }

        return [
            'status' => (string) $case->status,
            'recovery_kind' => (string) $case->recovery_kind,
            'not_before_at' => (string) $case->not_before_at,
            'expires_at' => (string) $case->expires_at,
            'approval_count' => DB::table('privileged_password_recovery_approvals')
                ->where('case_id', $case->id)->count(),
        ];
    }

    private function currentCaseRow(User $target): \stdClass
    {
        $case = DB::table('privileged_password_recovery_cases')
            ->where('target_user_id', $target->getKey())
            ->where('recovery_kind', PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT)
            ->whereIn('status', ['pending_approval', 'waiting_delay'])
            ->latest('created_at')->first();
        abort_unless($case instanceof \stdClass, Response::HTTP_CONFLICT);

        return $case;
    }

    private function recoveryHint(User $target): string
    {
        $hint = DB::table('user_identities')->where('user_id', $target->getKey())
            ->where('status', 'verified')->whereIn('type', ['phone', 'email'])
            ->orderBy('verified_at')->value('display_hint');

        return is_string($hint) ? $hint : 'verified contact';
    }

    private function guardLocal(Request $request): void
    {
        abort_unless(
            app()->environment(['local', 'testing'])
                && config('authentication.password.cutover_exclusion_reconciliation_enabled') === true
                && in_array($request->ip(), ['127.0.0.1', '::1'], true),
            Response::HTTP_NOT_FOUND,
        );
    }

    private function rejectBody(Request $request): void
    {
        if ($request->all() !== []) {
            throw $this->rejected();
        }
    }

    private function rejected(): ValidationException
    {
        return ValidationException::withMessages([
            'recovery' => ['The local excluded-account recovery operation could not be completed.'],
        ]);
    }
}
