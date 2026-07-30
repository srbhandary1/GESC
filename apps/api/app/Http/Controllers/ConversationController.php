<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Conversation\ConversationEventService;
use App\Conversation\ConversationSessionService;
use App\Conversation\ConversationTurnService;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\ConversationProviderException;
use App\Http\Requests\Conversation\ConversationTurnEventsRequest;
use App\Http\Requests\Conversation\CreateConversationTurnRequest;
use App\Http\Requests\Conversation\StartConversationSessionRequest;
use Illuminate\Http\JsonResponse;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationSessionService $sessions,
        private readonly ConversationTurnService $turns,
        private readonly ConversationEventService $events,
    ) {}

    public function start(
        StartConversationSessionRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->sessions->start(
                learner: $principal->user,
                personaKey: (string) $request->validated('persona_key'),
                scenarioKey: (string) $request->validated('scenario_key'),
                coachingMode: (string) $request->validated('coaching_mode'),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (ConversationIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (ConversationOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (ConversationProviderException) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(
            $result,
            $result['replayed'] === true
                ? Response::HTTP_OK
                : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function turn(
        CreateConversationTurnRequest $request,
        string $session,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->turns->create(
                learner: $principal->user,
                sessionId: $session,
                prototypeUtterance: (string) $request->validated(
                    'prototype_utterance',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (ConversationIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (ConversationOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (ConversationProviderException) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(
            $result,
            $result['replayed'] === true
                ? Response::HTTP_OK
                : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function events(
        ConversationTurnEventsRequest $request,
        string $turn,
    ): StreamedResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $events = $this->events->forTurn(
                learner: $principal->user,
                turnId: $turn,
            );
        } catch (ConversationOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (ConversationProviderException) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->stream(
            static function () use ($events): void {
                foreach ($events as $event) {
                    try {
                        $data = json_encode(
                            $event,
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_SLASHES,
                        );
                    } catch (JsonException) {
                        continue;
                    }

                    echo 'id: '.$event['sequence']."\n";
                    echo "event: conversation-status\n";
                    echo 'data: '.$data."\n\n";
                }
            },
            Response::HTTP_OK,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => 'text/event-stream; charset=UTF-8',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
