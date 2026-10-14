<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Mobile\MobileOAuthClient;
use App\Authentication\Mobile\MobilePasswordSignInGrant;
use App\Authentication\Mobile\MobilePasswordTokenIssuer;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class MobilePaidRegistrationController extends Controller
{
    public function __construct(
        private readonly LearnerSubscriptionService $subscriptions,
        private readonly MobilePasswordTokenIssuer $issuer,
        private readonly MobileTokenFamilyService $families,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request, [
            'phone',
            'password',
            'plan_id',
            'idempotency_key',
            'installation_id',
            'client_id',
        ], [
            'phone' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:512'],
            'plan_id' => ['required', 'string', 'ulid'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'installation_id' => ['required', 'ulid'],
            'client_id' => ['required', 'uuid'],
        ]);

        try {
            $client = $this->approvedClient((string) $validated['client_id']);
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

        if (
            ($payload['status'] ?? null) === 'completed'
            && ($payload['replayed'] ?? false) !== true
        ) {
            return $this->tokens(
                $client,
                (string) $validated['installation_id'],
                $payload,
                Response::HTTP_CREATED,
            );
        }

        $created = ($payload['replayed'] ?? false) !== true;

        return response()->json(
            $payload,
            $created ? Response::HTTP_CREATED : Response::HTTP_OK,
        )->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request, string $checkout): JsonResponse
    {
        $validated = $this->validated($request, [
            'payment_id',
            'signature',
            'installation_id',
            'client_id',
        ], [
            'payment_id' => ['required', 'string', 'max:80'],
            'signature' => ['required', 'string', 'max:128'],
            'installation_id' => ['required', 'ulid'],
            'client_id' => ['required', 'uuid'],
        ]);

        try {
            $client = $this->approvedClient((string) $validated['client_id']);
            $payload = $this->subscriptions->confirmPaidRegistration(
                $checkout,
                (string) $validated['payment_id'],
                (string) $validated['signature'],
            );
        } catch (PasswordAuthenticationFailed) {
            throw ValidationException::withMessages([
                'credentials' => ['Sign-in could not be completed.'],
            ]);
        } catch (EntitlementDenied) {
            throw ValidationException::withMessages([
                'plan_id' => ['Checkout could not be completed.'],
            ]);
        }

        if (($payload['status'] ?? null) === 'completed') {
            return $this->tokens($client, (string) $validated['installation_id'], $payload);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  list<string>  $allowed
     * @param  array<string, list<string>>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $allowed, array $rules): array
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unexpected request fields are not permitted.'],
            ]);
        }

        return $request->validate($rules);
    }

    private function approvedClient(string $clientId): MobileOAuthClient
    {
        $client = MobileOAuthClient::query()
            ->whereKey($clientId)
            ->where('revoked', false)
            ->first();

        if (
            ! $client instanceof MobileOAuthClient
            || ! $client->isApprovedNativeClient()
        ) {
            throw new PasswordAuthenticationFailed;
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function tokens(
        MobileOAuthClient $client,
        string $installationId,
        array $payload,
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        $checkoutRow = DB::table('subscription_checkouts')
            ->join('memberships', 'memberships.id', '=', 'subscription_checkouts.learner_membership_id')
            ->where('subscription_checkouts.id', $payload['id'] ?? '')
            ->first([
                'memberships.user_id',
                'memberships.id as membership_id',
            ]);
        $userId = $checkoutRow instanceof \stdClass ? $checkoutRow->user_id : null;
        $membershipId = $checkoutRow instanceof \stdClass && is_string($checkoutRow->membership_id ?? null)
            ? $checkoutRow->membership_id
            : null;
        $user = is_string($userId) ? User::query()->whereKey($userId)->first() : null;

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'plan_id' => ['Checkout could not be completed.'],
            ]);
        }

        $tokens = DB::transaction(function () use ($client, $user, $installationId, $membershipId): array {
            $issued = $this->issuer->issue($user, $client);
            $this->families->trackIssuedTokens(
                MobilePasswordSignInGrant::IDENTIFIER,
                $installationId,
                $issued['access_token'],
                $issued['refresh_token'],
                null,
            );
            if ($membershipId !== null) {
                $refreshRecord = $this->families->lockRefreshToken($issued['refresh_token']);
                if ($refreshRecord !== null) {
                    $this->families->selectMembership(
                        (string) $refreshRecord->family_id,
                        (string) $user->getKey(),
                        $membershipId,
                    );
                }
            }

            return $issued;
        }, 3);

        return response()->json($tokens, $status)
            ->header('Cache-Control', 'private, no-store');
    }
}
