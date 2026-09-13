<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Conversation\ConversationEventService;
use App\Conversation\ConversationSessionService;
use App\Conversation\ConversationTurnService;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\GuidanceSpeechService;
use App\Conversation\Observability\SpeechLatencyTelemetryService;
use App\Conversation\Observability\SpeechTelemetryIdempotencyConflict;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Realtime\RealtimeSpeechTicketService;
use App\Conversation\RoleplayStageService;
use App\Conversation\Speech\ConversationSpeechStreamer;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Http\Requests\Conversation\AdvanceConversationSessionStageRequest;
use App\Http\Requests\Conversation\ConversationTurnEventsRequest;
use App\Http\Requests\Conversation\CreateConversationTurnRequest;
use App\Http\Requests\Conversation\IssueRealtimeSpeechTicketRequest;
use App\Http\Requests\Conversation\RecordSpeechLatencyTelemetryRequest;
use App\Http\Requests\Conversation\StartConversationSessionRequest;
use App\Http\Requests\Conversation\SynthesizeConversationSpeechRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationSessionService $sessions,
        private readonly ConversationTurnService $turns,
        private readonly ConversationEventService $events,
        private readonly RoleplayStageService $stages,
        private readonly GuidanceSpeechService $speech,
        private readonly SpeechLatencyTelemetryService $latencyTelemetry,
        private readonly ConversationSpeechStreamer $speechStreamer,
        private readonly RealtimeSpeechTicketService $realtimeTickets,
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
                uiLocale: (string) $request->validated('ui_locale'),
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

    public function advanceStage(
        AdvanceConversationSessionStageRequest $request,
        string $session,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->stages->advance(
                learner: $principal->user,
                sessionId: $session,
                toStage: (string) $request->validated('to_stage'),
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

    public function speak(
        SynthesizeConversationSpeechRequest $request,
        string $session,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->speech->synthesize(
                learner: $principal->user,
                sessionId: $session,
                text: (string) $request->validated('text'),
                purpose: (string) $request->validated('purpose'),
            );
        } catch (ConversationOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (ConversationProviderException) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function issueRealtimeTicket(
        IssueRealtimeSpeechTicketRequest $request,
        string $session,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->realtimeTickets->issue(
                learner: $principal->user,
                sessionId: $session,
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (ConversationOperationRejected|VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (ConversationProviderException) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function realtimeSocket(
        Request $request,
        string $session,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $ticket = $request->query('ticket');
        if (! is_string($ticket) || $ticket === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->realtimeTickets->inspectSocket(
                $principal->user,
                $session,
                $ticket,
            );
        } catch (ConversationOperationRejected|VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result, Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Cache-Control', 'private, no-store');
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

    public function speechAudio(
        Request $request,
        string $turn,
        string $kind,
    ): StreamedResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            return $this->speechStreamer->stream(
                $principal->user,
                $turn,
                $kind,
            );
        } catch (ConversationOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    public function recordLatency(
        RecordSpeechLatencyTelemetryRequest $request,
        string $turn,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->latencyTelemetry->record(
                learner: $principal->user,
                turnId: $turn,
                idempotencyKey: (string) $request->validated('idempotency_key'),
                sample: [
                    'client_runtime' => (string) $request->validated('client_runtime'),
                    'network_class' => (string) $request->validated('network_class'),
                    'playback_outcome' => (string) $request->validated('playback_outcome'),
                    'release_to_ack_ms' => $this->nullableInteger(
                        $request->validated('release_to_ack_ms'),
                    ),
                    'release_to_first_signal_ms' => $this->nullableInteger(
                        $request->validated('release_to_first_signal_ms'),
                    ),
                    'release_to_first_audio_ms' => $this->nullableInteger(
                        $request->validated('release_to_first_audio_ms'),
                    ),
                    'turn_json_to_first_audio_ms' => $this->nullableInteger(
                        $request->validated('turn_json_to_first_audio_ms'),
                    ),
                ],
            );
        } catch (SpeechTelemetryIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (ConversationOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result, Response::HTTP_ACCEPTED)
            ->header('Cache-Control', 'private, no-store');
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
