<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Orchestration\ConversationOrchestrator;
use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderResult;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextRequest;
use App\Conversation\Providers\SpeechToTextResult;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Learning\Home\MeaningfulSpeakingDayProjector;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class ConversationTurnService
{
    public function __construct(
        private ConversationEnvironmentGate $environment,
        private LearnerAuthority $authority,
        private ConversationCommandIdempotency $commands,
        private ConversationTurnStateMachine $states,
        private ConversationProviderUsagePolicy $usagePolicy,
        private SpeechToTextProvider $speechToText,
        private ConversationOrchestrator $orchestrator,
        private TextToSpeechProvider $textToSpeech,
        private MeaningfulSpeakingDayProjector $speakingDays,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(
        User $learner,
        string $sessionId,
        string $prototypeUtterance,
        string $idempotencyKey,
    ): array {
        $this->environment->requireDeterministicFakes();
        $this->commands->assertIdempotencyKey($idempotencyKey);
        $utterance = trim($prototypeUtterance);

        if (
            $utterance === ''
            || preg_match('//u', $utterance) !== 1
            || mb_strlen($utterance) > (int) config(
                'conversation.maximum_utterance_characters',
                500,
            )
        ) {
            throw new ConversationOperationRejected;
        }

        return DB::transaction(function () use (
            $learner,
            $sessionId,
            $utterance,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $session = $this->session(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                sessionId: $sessionId,
            );
            $fingerprint = $this->commands->fingerprint([
                'prototype_utterance' => $utterance,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'conversation.turn.create',
                aggregateType: 'conversation_session',
                aggregateId: $sessionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                return $this->existing(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    session: $session,
                    turnId: $this->string($replay, 'result_id'),
                    utterance: $utterance,
                    replayed: true,
                );
            }

            $now = Date::now()->toImmutable();
            $turnId = (string) Str::ulid();
            $turnNumber = (int) DB::table('conversation_turns')
                ->where('organization_id', $context->organizationId)
                ->where('conversation_session_id', $sessionId)
                ->max('turn_number') + 1;
            $state = 'accepted';

            DB::table('conversation_turns')->insert([
                'id' => $turnId,
                'organization_id' => $context->organizationId,
                'conversation_session_id' => $sessionId,
                'learner_membership_id' => $membershipId,
                'turn_number' => $turnNumber,
                'status' => $state,
                'utterance_digest' => $this->digest($utterance),
                'transcript_digest' => null,
                'assistant_reply_digest' => null,
                'failed_stage' => null,
                'failure_code' => null,
                'accepted_at' => $now,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->recordEvent(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                sessionId: $sessionId,
                turnId: $turnId,
                state: $state,
                eventCode: 'turn.accepted',
            );

            $transcript = null;
            $assistantReply = null;
            $understoodIntent = null;
            $priorityCorrection = null;
            $safetyState = null;
            $speech = null;

            try {
                $state = $this->transition(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    from: $state,
                    to: 'transcribing',
                    eventCode: 'turn.transcribing',
                    providerStage: 'stt',
                );
                $speechResult = $this->provider(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    state: $state,
                    stage: 'stt',
                    operation: fn (): SpeechToTextResult => $this
                        ->speechToText
                        ->transcribe(new SpeechToTextRequest(
                            interactionId: $turnId,
                            prototypeUtterance: $utterance,
                        )),
                );

                if (! $speechResult instanceof SpeechToTextResult) {
                    throw new ConversationOperationRejected;
                }

                $transcript = $speechResult->transcript;
                $state = $this->transition(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    from: $state,
                    to: 'generating_reply',
                    eventCode: 'turn.generating_reply',
                    providerStage: 'model',
                    updates: [
                        'transcript_digest' => $this->digest($transcript),
                    ],
                );
                $modelResult = $this->provider(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    state: $state,
                    stage: 'model',
                    operation: fn (): ConversationModelResult => $this
                        ->orchestrator
                        ->respond(
                            organizationId: $context->organizationId,
                            membershipId: $membershipId,
                            sessionId: $sessionId,
                            turnId: $turnId,
                            transcript: $transcript,
                            personaStyleCode: $this->string(
                                $session,
                                'persona_style_code',
                            ),
                            scenarioResponseCode: $this->string(
                                $session,
                                'scenario_response_code',
                            ),
                            coachingMode: $this->string(
                                $session,
                                'coaching_mode',
                            ),
                            uiLocale: $this->string(
                                $session,
                                'ui_locale',
                            ),
                        ),
                );

                if (! $modelResult instanceof ConversationModelResult) {
                    throw new ConversationOperationRejected;
                }

                $assistantReply = $modelResult->assistantReply;
                $understoodIntent = $modelResult->understoodIntent;
                $priorityCorrection = $modelResult->priorityCorrection;
                $safetyState = $modelResult->safetyState;
                $state = $this->transition(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    from: $state,
                    to: 'synthesizing',
                    eventCode: 'turn.synthesizing',
                    providerStage: 'tts',
                    updates: [
                        'assistant_reply_digest' => $this->digest(
                            $assistantReply,
                        ),
                    ],
                );
                $textToSpeechResult = $this->provider(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    state: $state,
                    stage: 'tts',
                    operation: fn (): TextToSpeechResult => $this
                        ->synthesizeSpeech(
                            turnId: $turnId,
                            text: $assistantReply,
                            purpose: GuidanceTtsPolicy::PURPOSE_MODEL_EN,
                            uiLocale: $this->string($session, 'ui_locale'),
                        ),
                );

                if (! $textToSpeechResult instanceof TextToSpeechResult) {
                    throw new ConversationOperationRejected;
                }

                $guidanceSpeechResult = null;
                $guidanceText = $priorityCorrection?->explanationL1;

                if (is_string($guidanceText) && trim($guidanceText) !== '') {
                    try {
                        $guidanceSpeechResult = $this->synthesizeSpeech(
                            turnId: $turnId,
                            text: $guidanceText,
                            purpose: GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
                            uiLocale: $this->string($session, 'ui_locale'),
                        );
                        $this->usagePolicy->assertAllowed(
                            'tts',
                            $guidanceSpeechResult->usage(),
                        );
                        $this->recordUsage(
                            organizationId: $context->organizationId,
                            membershipId: $membershipId,
                            sessionId: $sessionId,
                            turnId: $turnId,
                            stage: 'tts',
                            attempt: 2,
                            usage: $guidanceSpeechResult->usage(),
                            resultStatus: 'succeeded',
                        );
                    } catch (ConversationProviderException|ConversationOperationRejected) {
                        $guidanceSpeechResult = null;
                    }
                }

                $speech = $this->speechPayload(
                    $textToSpeechResult,
                    $guidanceSpeechResult,
                );
                $state = $this->transition(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    from: $state,
                    to: 'completed',
                    eventCode: 'turn.completed',
                    updates: ['completed_at' => Date::now()->toImmutable()],
                );
                $this->speakingDays->reconcileTurn(
                    $context->organizationId,
                    $membershipId,
                    $turnId,
                );
            } catch (ConversationProviderException $exception) {
                $completedAt = Date::now()->toImmutable();
                $state = $this->transition(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    from: $state,
                    to: 'failed',
                    eventCode: 'turn.failed',
                    providerStage: $this->stageForState($state),
                    providerAttempt: (int) config(
                        'conversation.maximum_provider_attempts',
                        2,
                    ),
                    updates: [
                        'completed_at' => $completedAt,
                        'failed_stage' => $this->stageForState($state),
                        'failure_code' => $exception->safeCode,
                    ],
                );
                $transcript = null;
                $assistantReply = null;
                $understoodIntent = null;
                $priorityCorrection = null;
                $safetyState = null;
                $speech = null;
            }

            $completedAt = Date::now()->toImmutable();
            $this->commands->record(
                actor: $learner,
                operation: 'conversation.turn.create',
                aggregateType: 'conversation_session',
                aggregateId: $sessionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'conversation_turn',
                resultId: $turnId,
                completedAt: $completedAt,
            );

            return $this->result(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                sessionId: $sessionId,
                turnId: $turnId,
                turnNumber: $turnNumber,
                status: $state,
                transcript: $transcript,
                assistantReply: $assistantReply,
                understoodIntent: $understoodIntent,
                priorityCorrection: $priorityCorrection,
                safetyState: $safetyState,
                speech: $speech,
                replayed: false,
            );
        }, 3);
    }

    private function session(
        string $organizationId,
        string $membershipId,
        string $sessionId,
    ): stdClass {
        $session = DB::table('conversation_sessions as session')
            ->join('persona_versions as persona_version', function ($join): void {
                $join
                    ->on(
                        'persona_version.organization_id',
                        '=',
                        'session.organization_id',
                    )
                    ->on(
                        'persona_version.id',
                        '=',
                        'session.persona_version_id',
                    )
                    ->on(
                        'persona_version.persona_id',
                        '=',
                        'session.persona_id',
                    );
            })
            ->join('scenario_versions as scenario_version', function ($join): void {
                $join
                    ->on(
                        'scenario_version.organization_id',
                        '=',
                        'session.organization_id',
                    )
                    ->on(
                        'scenario_version.id',
                        '=',
                        'session.scenario_version_id',
                    )
                    ->on(
                        'scenario_version.scenario_id',
                        '=',
                        'session.scenario_id',
                    );
            })
            ->where('session.organization_id', $organizationId)
            ->where('session.learner_membership_id', $membershipId)
            ->where('session.id', $sessionId)
            ->where('session.status', 'active')
            ->lockForUpdate()
            ->first([
                'session.id',
                'session.coaching_mode',
                'session.ui_locale',
                'persona_version.style_code as persona_style_code',
                'scenario_version.response_code as scenario_response_code',
            ]);

        if (! $session instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        return $session;
    }

    /**
     * @param  callable(): ProviderResult  $operation
     */
    private function provider(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $state,
        string $stage,
        callable $operation,
    ): ProviderResult {
        $maximumAttempts = max(
            1,
            (int) config('conversation.maximum_provider_attempts', 2),
        );

        for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
            try {
                $result = $operation();
                $this->usagePolicy->assertAllowed($stage, $result->usage());
                $this->recordUsage(
                    organizationId: $organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    stage: $stage,
                    attempt: $attempt,
                    usage: $result->usage(),
                    resultStatus: 'succeeded',
                );

                return $result;
            } catch (ConversationProviderException $exception) {
                $usage = new ProviderUsage(
                    providerKey: $exception->providerKey,
                    modelVersion: $exception->modelVersion,
                    latencyMilliseconds: $exception
                        ->latencyMilliseconds,
                    inputUnits: $exception->inputUnits,
                    outputUnits: $exception->outputUnits,
                    estimatedCostMicrounits: 0,
                );
                $this->usagePolicy->assertAllowed($stage, $usage);
                $this->recordUsage(
                    organizationId: $organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    stage: $stage,
                    attempt: $attempt,
                    usage: $usage,
                    resultStatus: 'failed',
                );

                if ($attempt >= $maximumAttempts) {
                    throw $exception;
                }

                $this->recordEvent(
                    organizationId: $organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    state: $state,
                    eventCode: 'turn.provider_retrying',
                    providerStage: $stage,
                    providerAttempt: $attempt,
                );
            }
        }

        throw new ConversationOperationRejected;
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function transition(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $from,
        string $to,
        string $eventCode,
        ?string $providerStage = null,
        ?int $providerAttempt = 1,
        array $updates = [],
    ): string {
        $this->states->assertTransition($from, $to);
        $changed = DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_session_id', $sessionId)
            ->where('id', $turnId)
            ->where('status', $from)
            ->update([
                ...$updates,
                'status' => $to,
                'updated_at' => Date::now()->toImmutable(),
            ]);

        if ($changed !== 1) {
            throw new ConversationOperationRejected;
        }

        $this->recordEvent(
            organizationId: $organizationId,
            membershipId: $membershipId,
            sessionId: $sessionId,
            turnId: $turnId,
            state: $to,
            eventCode: $eventCode,
            providerStage: $providerStage,
            providerAttempt: $providerStage === null
                ? null
                : $providerAttempt,
        );

        return $to;
    }

    private function recordEvent(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $state,
        string $eventCode,
        ?string $providerStage = null,
        ?int $providerAttempt = null,
    ): void {
        $sequence = (int) DB::table('conversation_turn_events')
            ->where('organization_id', $organizationId)
            ->where('conversation_turn_id', $turnId)
            ->max('sequence') + 1;

        DB::table('conversation_turn_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'conversation_turn_id' => $turnId,
            'conversation_session_id' => $sessionId,
            'learner_membership_id' => $membershipId,
            'sequence' => $sequence,
            'state' => $state,
            'event_code' => $eventCode,
            'provider_stage' => $providerStage,
            'provider_attempt' => $providerAttempt,
            'recorded_at' => Date::now()->toImmutable(),
        ]);
    }

    private function recordUsage(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $stage,
        int $attempt,
        ProviderUsage $usage,
        string $resultStatus,
    ): void {
        DB::table('ai_cost_ledger')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'conversation_turn_id' => $turnId,
            'conversation_session_id' => $sessionId,
            'learner_membership_id' => $membershipId,
            'provider_stage' => $stage,
            'provider_key' => $usage->providerKey,
            'model_version' => $usage->modelVersion,
            'provider_attempt' => $attempt,
            'latency_ms' => $usage->latencyMilliseconds,
            'input_units' => $usage->inputUnits,
            'output_units' => $usage->outputUnits,
            'estimated_cost_microunits' => $usage
                ->estimatedCostMicrounits,
            'result_status' => $resultStatus,
            'recorded_at' => Date::now()->toImmutable(),
        ]);
    }

    private function stageForState(string $state): string
    {
        return match ($state) {
            'transcribing' => 'stt',
            'generating_reply' => 'model',
            'synthesizing' => 'tts',
            default => throw new ConversationOperationRejected,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function existing(
        string $organizationId,
        string $membershipId,
        stdClass $session,
        string $turnId,
        string $utterance,
        bool $replayed,
    ): array {
        $turn = DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_session_id', $this->string($session, 'id'))
            ->where('id', $turnId)
            ->first([
                'turn_number',
                'status',
                'utterance_digest',
                'transcript_digest',
                'assistant_reply_digest',
            ]);

        if (
            ! $turn instanceof stdClass
            || ! hash_equals(
                $this->string($turn, 'utterance_digest'),
                $this->digest($utterance),
            )
        ) {
            throw new ConversationOperationRejected;
        }

        $status = $this->string($turn, 'status');

        if ($status !== 'completed') {
            return $this->result(
                organizationId: $organizationId,
                membershipId: $membershipId,
                sessionId: $this->string($session, 'id'),
                turnId: $turnId,
                turnNumber: $this->integer($turn, 'turn_number'),
                status: $status,
                transcript: null,
                assistantReply: null,
                understoodIntent: null,
                priorityCorrection: null,
                safetyState: null,
                speech: null,
                replayed: $replayed,
            );
        }

        $speechResult = $this->speechToText->transcribe(
            new SpeechToTextRequest(
                interactionId: $turnId,
                prototypeUtterance: $utterance,
            ),
        );
        $modelResult = $this->orchestrator->respond(
            organizationId: $organizationId,
            membershipId: $membershipId,
            sessionId: $this->string($session, 'id'),
            turnId: $turnId,
            transcript: $speechResult->transcript,
            personaStyleCode: $this->string(
                $session,
                'persona_style_code',
            ),
            scenarioResponseCode: $this->string(
                $session,
                'scenario_response_code',
            ),
            coachingMode: $this->string(
                $session,
                'coaching_mode',
            ),
            uiLocale: $this->string(
                $session,
                'ui_locale',
            ),
            persistAdvisory: false,
        );
        $uiLocale = $this->string($session, 'ui_locale');
        $textToSpeechResult = $this->synthesizeSpeech(
            turnId: $turnId,
            text: $modelResult->assistantReply,
            purpose: GuidanceTtsPolicy::PURPOSE_MODEL_EN,
            uiLocale: $uiLocale,
        );
        $guidanceSpeechResult = null;
        $guidanceText = $modelResult->priorityCorrection?->explanationL1;

        if (is_string($guidanceText) && trim($guidanceText) !== '') {
            $guidanceSpeechResult = $this->synthesizeSpeech(
                turnId: $turnId,
                text: $guidanceText,
                purpose: GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
                uiLocale: $uiLocale,
            );
        }

        if (
            ! hash_equals(
                $this->string($turn, 'transcript_digest'),
                $this->digest($speechResult->transcript),
            )
            || ! hash_equals(
                $this->string($turn, 'assistant_reply_digest'),
                $this->digest($modelResult->assistantReply),
            )
        ) {
            throw new ConversationOperationRejected;
        }

        return $this->result(
            organizationId: $organizationId,
            membershipId: $membershipId,
            sessionId: $this->string($session, 'id'),
            turnId: $turnId,
            turnNumber: $this->integer($turn, 'turn_number'),
            status: $status,
            transcript: $speechResult->transcript,
            assistantReply: $modelResult->assistantReply,
            understoodIntent: $modelResult->understoodIntent,
            priorityCorrection: $modelResult->priorityCorrection,
            safetyState: $modelResult->safetyState,
            speech: $this->speechPayload(
                $textToSpeechResult,
                $guidanceSpeechResult,
            ),
            replayed: $replayed,
        );
    }

    /**
     * @param  array<string, bool|string|null>|null  $speech
     * @return array<string, mixed>
     */
    private function result(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        int $turnNumber,
        string $status,
        ?string $transcript,
        ?string $assistantReply,
        ?string $understoodIntent,
        ?PriorityCorrection $priorityCorrection,
        ?string $safetyState,
        ?array $speech,
        bool $replayed,
    ): array {
        $usage = DB::table('ai_cost_ledger')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_turn_id', $turnId)
            ->selectRaw(
                'COALESCE(SUM(latency_ms), 0) as total_latency_ms, COALESCE(SUM(estimated_cost_microunits), 0) as estimated_cost_microunits, COUNT(*) as provider_attempts',
            )
            ->first();

        if (! $usage instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        return [
            'id' => $turnId,
            'conversation_session_id' => $sessionId,
            'turn_number' => $turnNumber,
            'status' => $status,
            'transcript' => $transcript,
            'understood_intent' => $understoodIntent,
            'assistant_reply' => $assistantReply,
            'priority_correction' => $priorityCorrection?->toArray(),
            'safety_state' => $safetyState,
            'speech' => $speech,
            'usage' => [
                'provider_attempts' => $this->integer(
                    $usage,
                    'provider_attempts',
                ),
                'total_latency_ms' => $this->integer(
                    $usage,
                    'total_latency_ms',
                ),
                'estimated_cost_microunits' => $this->integer(
                    $usage,
                    'estimated_cost_microunits',
                ),
            ],
            'events_url' => sprintf(
                '/api/v1/conversation-turns/%s/events',
                $turnId,
            ),
            'replayed' => $replayed,
        ];
    }

    private function synthesizeSpeech(
        string $turnId,
        string $text,
        string $purpose,
        string $uiLocale,
    ): TextToSpeechResult {
        return $this->textToSpeech->synthesize(new TextToSpeechRequest(
            interactionId: $turnId,
            text: $text,
            voice: GuidanceTtsPolicy::voiceFor($purpose, $uiLocale),
            purpose: $purpose,
            uiLocale: $uiLocale,
        ));
    }

    /**
     * @return array{
     *   playback_available: bool,
     *   rendering_mode: string,
     *   model_audio_base64: ?string,
     *   model_audio_mime_type: ?string,
     *   guidance_audio_base64: ?string,
     *   guidance_audio_mime_type: ?string
     * }
     */
    private function speechPayload(
        TextToSpeechResult $modelSpeech,
        ?TextToSpeechResult $guidanceSpeech,
    ): array {
        $modelAudio = $modelSpeech->audioBytes;
        $guidanceAudio = $guidanceSpeech?->audioBytes;

        return [
            'playback_available' => $modelSpeech->playbackAvailable
                || ($guidanceSpeech?->playbackAvailable ?? false),
            'rendering_mode' => $modelSpeech->renderingMode,
            'model_audio_base64' => is_string($modelAudio) && $modelAudio !== ''
                ? base64_encode($modelAudio)
                : null,
            'model_audio_mime_type' => is_string($modelAudio) && $modelAudio !== ''
                ? ($modelSpeech->mimeType ?? 'audio/wav')
                : null,
            'guidance_audio_base64' => is_string($guidanceAudio) && $guidanceAudio !== ''
                ? base64_encode($guidanceAudio)
                : null,
            'guidance_audio_mime_type' => is_string($guidanceAudio) && $guidanceAudio !== ''
                ? ($guidanceSpeech?->mimeType ?? 'audio/wav')
                : null,
        ];
    }

    private function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new ConversationOperationRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (
            (! is_int($value) && ! is_string($value))
            || ! is_numeric($value)
        ) {
            throw new ConversationOperationRejected;
        }

        return (int) $value;
    }
}
