<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Providers\ConversationProviderException;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LevelSessions\LevelSessionAudioStreamer;
use App\Learning\LevelSessions\LevelSessionDeliveryService;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LearnerLevelSessionController extends Controller
{
    public function __construct(
        private readonly LevelSessionDeliveryService $sessions,
        private readonly LevelSessionAudioStreamer $audio,
        private readonly OrganizationContextManager $contexts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->contexts->current();

        return response()->json([
            'levels' => $this->sessions->listLevels($context),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function sessions(Request $request, int $levelNumber): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $context = $this->contexts->current();

        try {
            $payload = $this->sessions->listSessions(
                $context,
                $principal->user,
                $levelNumber,
            );
        } catch (LearningOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        int $levelNumber,
        string $partId,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $context = $this->contexts->current();

        try {
            $payload = $this->sessions->sessionDetail(
                $context,
                $principal->user,
                $levelNumber,
                $partId,
            );
        } catch (LearningOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function audio(
        Request $request,
        int $levelNumber,
        string $partId,
    ): StreamedResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $context = $this->contexts->current();

        try {
            return $this->audio->stream(
                $context,
                $principal->user,
                $levelNumber,
                $partId,
            );
        } catch (LearningOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    public function complete(
        Request $request,
        int $levelNumber,
        string $partId,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $context = $this->contexts->current();
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $result = $this->sessions->completeSession(
                $context,
                $principal->user,
                $levelNumber,
                $partId,
                (string) $validated['idempotency_key'],
            );
        } catch (LearningOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result, $result['replayed'] ? 200 : 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function submitCheck(
        Request $request,
        int $levelNumber,
        string $partId,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $context = $this->contexts->current();
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
            'answers' => ['required', 'array'],
            'answers.*' => ['integer', 'min:0', 'max:20'],
        ]);

        try {
            $result = $this->sessions->submitCheck(
                $context,
                $principal->user,
                $levelNumber,
                $partId,
                $validated['answers'],
                (string) $validated['idempotency_key'],
            );
        } catch (LearningOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result, $result['replayed'] ? 200 : 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function startConversation(
        Request $request,
        int $levelNumber,
        string $partId,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $context = $this->contexts->current();
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:120'],
            'ui_locale' => ['required', 'string', 'max:16'],
        ]);

        try {
            $result = $this->sessions->startBoundConversation(
                $context,
                $principal->user,
                $levelNumber,
                $partId,
                (string) $validated['ui_locale'],
                (string) $validated['idempotency_key'],
            );
        } catch (ConversationIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (ConversationProviderException) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (LearningOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $result,
            ($result['replayed'] ?? false) === true ? 200 : 201,
        )->header('Cache-Control', 'private, no-store');
    }
}
