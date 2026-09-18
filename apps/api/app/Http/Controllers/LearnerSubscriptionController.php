<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\LearnerSubscriptionService;
use App\Http\Requests\Learner\ConfirmSubscriptionCheckoutRequest;
use App\Http\Requests\Learner\CreateSubscriptionCheckoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LearnerSubscriptionController extends Controller
{
    public function __construct(
        private readonly EntitlementAccess $access,
        private readonly LearnerSubscriptionService $subscriptions,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        return response()->json($this->access->snapshot($principal->user))
            ->header('Cache-Control', 'private, no-store');
    }

    public function plans(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        return response()->json($this->subscriptions->catalogue($principal->user))
            ->header('Cache-Control', 'private, no-store');
    }

    public function checkout(CreateSubscriptionCheckoutRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $payload = $this->subscriptions->checkout(
            $principal->user,
            (string) $request->validated('plan_id'),
            (string) $request->validated('idempotency_key'),
        );
        $created = $payload['replayed'] !== true;

        return response()->json(
            $payload,
            $created ? Response::HTTP_CREATED : Response::HTTP_OK,
        )->header('Cache-Control', 'private, no-store');
    }

    public function reconcile(Request $request, string $checkout): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        return response()->json(
            $this->subscriptions->reconcileCheckout($principal->user, $checkout),
        )->header('Cache-Control', 'private, no-store');
    }

    public function confirm(
        ConfirmSubscriptionCheckoutRequest $request,
        string $checkout,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        return response()->json($this->subscriptions->confirmCheckout(
            $principal->user,
            $checkout,
            (string) $request->validated('payment_id'),
            (string) $request->validated('signature'),
        ))->header('Cache-Control', 'private, no-store');
    }
}
