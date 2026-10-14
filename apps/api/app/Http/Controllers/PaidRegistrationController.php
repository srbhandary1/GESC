<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Entitlements\LearnerSubscriptionService;
use App\Identity\OrganizationSelectionStore;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

final class PaidRegistrationController extends Controller
{
    public function __construct(
        private readonly LearnerSubscriptionService $subscriptions,
        private readonly AuthenticationSessionService $sessions,
    ) {}

    public function plans(): JsonResponse
    {
        try {
            $catalogue = $this->subscriptions->publicCatalogue();
        } catch (EntitlementDenied) {
            throw ValidationException::withMessages([
                'plan_id' => ['Checkout could not be completed.'],
            ]);
        }

        return response()->json($catalogue)
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:512'],
            'plan_id' => ['required', 'string', 'ulid'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        try {
            $payload = $this->subscriptions->beginPaidRegistration(
                (string) $validated['phone'],
                (string) $validated['password'],
                (string) $validated['plan_id'],
                (string) $validated['idempotency_key'],
                $request,
            );
        } catch (PasswordAuthenticationFailed) {
            throw ValidationException::withMessages([
                'credentials' => ['Sign-in could not be completed.'],
            ]);
        } catch (EntitlementDenied) {
            throw ValidationException::withMessages([
                'plan_id' => ['Checkout could not be completed.'],
            ]);
        } finally {
            $request->request->remove('password');
        }

        $payload = $this->attachBrowserSession($request, $payload);
        $created = ($payload['replayed'] ?? false) !== true;

        return response()->json(
            $payload,
            $created ? Response::HTTP_CREATED : Response::HTTP_OK,
        )->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request, string $checkout): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'string', 'max:80'],
            'signature' => ['required', 'string', 'max:128'],
        ]);

        try {
            $payload = $this->subscriptions->confirmPaidRegistration(
                $checkout,
                (string) $validated['payment_id'],
                (string) $validated['signature'],
            );
        } catch (EntitlementDenied) {
            throw ValidationException::withMessages([
                'plan_id' => ['Checkout could not be completed.'],
            ]);
        }

        return response()->json($this->attachBrowserSession($request, $payload))
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attachBrowserSession(Request $request, array $payload): array
    {
        if (($payload['status'] ?? null) !== 'completed') {
            return $payload;
        }

        $checkoutId = $payload['id'] ?? null;
        $checkoutRow = is_string($checkoutId)
            ? DB::table('subscription_checkouts')
                ->join('memberships', 'memberships.id', '=', 'subscription_checkouts.learner_membership_id')
                ->where('subscription_checkouts.id', $checkoutId)
                ->first([
                    'memberships.user_id',
                    'memberships.id as membership_id',
                    'memberships.organization_id',
                ])
            : null;
        $userId = $checkoutRow instanceof stdClass ? $checkoutRow->user_id : null;
        $user = is_string($userId) ? User::query()->whereKey($userId)->first() : null;

        if (! $user instanceof User) {
            return $payload;
        }

        $session = $this->sessions->start($user, $request);
        if (
            is_string($checkoutRow->membership_id ?? null)
            && is_string($checkoutRow->organization_id ?? null)
        ) {
            DB::table('authentication_sessions')
                ->where('id', (string) $session->getKey())
                ->update([
                    'selected_organization_id' => $checkoutRow->organization_id,
                    'selected_membership_id' => $checkoutRow->membership_id,
                    'updated_at' => now(),
                ]);
            if ($request->hasSession()) {
                $request->session()->put(
                    OrganizationSelectionStore::WEB_SESSION_KEY,
                    $checkoutRow->membership_id,
                );
            }
        }
        $payload['user'] = ['id' => (string) $user->getKey()];
        $payload['session'] = [
            'id' => (string) $session->getKey(),
            'expires_at' => $session->idle_expires_at->min($session->absolute_expires_at)->toIso8601String(),
        ];

        return $payload;
    }
}
