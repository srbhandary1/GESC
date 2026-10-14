<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Avatar\ConversationSpeechPayloadBuilder;
use App\Conversation\Capacity\ConversationCapacityAdmission;
use App\Conversation\Dialogue\ConversationDialogueService;
use App\Conversation\Dialogue\DialogueTurnReservationService;
use App\Conversation\Dialogue\LocalDialogueGreeting;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Observability\ProviderAttemptObservationRecorder;
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
use App\Conversation\Speech\ConversationSpeechArtifactStore;
use App\Conversation\Speech\SynthesizeDeferredGuidanceSpeech;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Conversation\Support\LessonDrillStrictTurnResponder;
use App\Conversation\Support\SpokenGrammarNotation;
use App\Conversation\Voice\VoiceConsentService;
use App\Entitlements\EntitlementAccess;
use App\Learning\Home\MeaningfulSpeakingDayProjector;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

/** @phpstan-import-type ConversationSpeechPayload from ConversationSpeechPayloadBuilder */
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
        private EntitlementAccess $entitlements,
        private ConversationSpeechPayloadBuilder $speechPayloadBuilder,
        private ProviderAttemptObservationRecorder $observations,
        private ConversationSpeechArtifactStore $speechArtifacts,
        private ConversationSessionBudget $sessionBudget,
        private ConversationDialogueService $dialogue,
        private VoiceConsentService $consents,
        private DialogueTurnReservationService $reservations,
        private ConversationCapacityAdmission $capacity,
    ) {}

    /**
     * @param  array<string, mixed>  $dialogueInput
     * @return array<string, mixed>
     */
    public function create(
        User $learner,
        string $sessionId,
        string $prototypeUtterance,
        string $idempotencyKey,
        array $dialogueInput = [],
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

        $reservationId = $this->reservations->reserve(
            $learner, $sessionId, $idempotencyKey,
            $this->commands->fingerprint(['prototype_utterance' => $utterance, 'dialogue_input' => $dialogueInput]),
            function (stdClass $state, stdClass $session) use ($learner, $sessionId, $utterance, $dialogueInput): int {
                $prepared = $this->dialogue->prepare($state, $dialogueInput, $utterance, (string) $session->ui_locale);
                $this->sessionBudget->assertCanAcceptLearnerTurn((string) $state->organization_id, $sessionId);
                $this->entitlements->assertLiveAiAvailable($learner);
                if (! in_array($dialogueInput['action'] ?? '', ['confused', 'decline_explanation'], true)
                    && LocalDialogueGreeting::reply($utterance, $prepared) === null) {
                    $this->capacity->admit((string) $state->organization_id, (string) $state->learner_membership_id);
                }

                return (int) $state->revision;
            },
        );

        return DB::transaction(function () use (
            $learner,
            $sessionId,
            $utterance,
            $idempotencyKey,
            $dialogueInput,
            $reservationId,
        ): array {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $session = $this->session(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                sessionId: $sessionId,
            );
            $dialogueState = $this->dialogue->state($context->organizationId, $membershipId, $sessionId);
            if ($dialogueState !== null) {
                $this->consents->activeEvent($context->organizationId, $membershipId, VoiceConsentService::PROVIDER);
            }
            $fingerprint = $this->commands->fingerprint([
                'prototype_utterance' => $utterance,
                ...($dialogueState !== null ? ['dialogue_input' => $dialogueInput] : []),
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
                if ($dialogueState !== null) {
                    return $this->dialogue->replay($dialogueState, $this->string($replay, 'result_id'));
                }

                return $this->existing(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    session: $session,
                    turnId: $this->string($replay, 'result_id'),
                    utterance: $utterance,
                    replayed: true,
                );
            }
            $dialogueContext = $dialogueState === null ? [] : $this->dialogue->prepare($dialogueState, $dialogueInput, $utterance, $this->string($session, 'ui_locale'));
            if (is_array($dialogueContext['lesson'] ?? null)) {
                $this->entitlements->assertLevel($learner, (int) $dialogueContext['lesson']['level_number']);
                $this->entitlements->assertSessionPart($learner, (string) $dialogueContext['lesson']['part_id']);
            }
            $this->sessionBudget->assertCanAcceptLearnerTurn($context->organizationId, $sessionId);
            $this->entitlements->assertLiveAiAvailable($learner);
            if ($dialogueState === null && ! (new LessonDrillStrictTurnResponder)->applies($this->lessonDrillFor($context->organizationId, $membershipId, $sessionId))) {
                $this->capacity->admit($context->organizationId, $membershipId);
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
            $modelResult = null;

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
                    attemptLimit: $dialogueState === null ? null : 1,
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
                $lessonDrill = $this->lessonDrillFor(
                    $context->organizationId,
                    $membershipId,
                    $sessionId,
                );
                $grammarDrill = $lessonDrill !== [];
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
                            lessonDrill: $lessonDrill,
                            dialogue: $dialogueContext,
                        ),
                    attemptLimit: $dialogueState === null ? null : 1,
                );

                if (! $modelResult instanceof ConversationModelResult) {
                    throw new ConversationOperationRejected;
                }

                $assistantReply = $modelResult->assistantReply;
                $understoodIntent = $modelResult->understoodIntent;
                if (
                    $lessonDrill !== []
                    && $understoodIntent
                        === LessonDrillStrictTurnResponder::INTENT_PATTERN_ACCEPTED
                ) {
                    $this->recordAcceptedLessonDrillPattern(
                        organizationId: $context->organizationId,
                        membershipId: $membershipId,
                        sessionId: $sessionId,
                    );
                }
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
                $textOnlyExplanation = $dialogueState !== null && ($modelResult->safetyState !== 'ok'
                    || (($dialogueContext['flow'] ?? '') === 'free_speech'
                        && ($dialogueContext['action'] ?? '') === 'accept_explanation'));
                try {
                    $textToSpeechResult = $textOnlyExplanation
                        ? new TextToSpeechResult(false, 'text-only-explanation', new ProviderUsage('project-text-only', 'text-only-v1', 0, 0, 0, 0, dispatchKind: 'local_execution'))
                        : $this->provider(
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
                                    purpose: $modelResult->replyLocale === 'en-IN' ? GuidanceTtsPolicy::PURPOSE_MODEL_EN : GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
                                    uiLocale: $modelResult->replyLocale,
                                    grammarDrill: $grammarDrill,
                                ),
                            attemptLimit: $dialogueState === null ? null : 1,
                        );
                } catch (ConversationProviderException $exception) {
                    if ($dialogueState === null) {
                        throw $exception;
                    }
                    $textToSpeechResult = new TextToSpeechResult(false, 'text-first', new ProviderUsage('project-text-first', 'text-first-v1', 0, 0, 0, 0));
                }

                if (! $textToSpeechResult instanceof TextToSpeechResult) {
                    throw new ConversationOperationRejected;
                }

                $guidanceText = $priorityCorrection?->explanationL1;
                $guidanceSpeech = null;
                if ($dialogueState !== null && is_string($guidanceText) && $guidanceText !== '') {
                    try {
                        $candidate = $this->provider(
                            organizationId: $context->organizationId, membershipId: $membershipId,
                            sessionId: $sessionId, turnId: $turnId, state: $state, stage: 'tts',
                            operation: fn (): TextToSpeechResult => $this->synthesizeSpeech($turnId, $guidanceText, GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1, $this->string($session, 'ui_locale')),
                            attemptOffset: 1, attemptLimit: 1,
                        );
                        $guidanceSpeech = $candidate instanceof TextToSpeechResult ? $candidate : null;
                    } catch (ConversationProviderException) {
                        // The failed dispatch is metered; validated correction text still works.
                    }
                }
                $modelArtifact = $dialogueState !== null ? null : $this->speechArtifacts->persist(
                    $context->organizationId,
                    $membershipId,
                    $turnId,
                    ConversationSpeechArtifactStore::KIND_MODEL,
                    $textToSpeechResult,
                );
                $guidancePending = $dialogueState === null && is_string($guidanceText) && trim($guidanceText) !== '';
                if ($guidancePending) {
                    $this->deferGuidance(
                        organizationId: $context->organizationId,
                        membershipId: $membershipId,
                        sessionId: $sessionId,
                        turnId: $turnId,
                        guidanceText: $guidanceText,
                        uiLocale: $this->string($session, 'ui_locale'),
                    );
                }

                $speech = $this->speechPayloadBuilder->build(
                    modelSpeech: $textToSpeechResult,
                    guidanceSpeech: $guidanceSpeech,
                    modelText: $assistantReply,
                    guidanceText: $guidanceText,
                    modelAudioStreamPath: $modelArtifact['stream_path'] ?? null,
                    guidanceAudioStreamPath: $guidancePending
                        ? $this->speechArtifacts->streamPath(
                            $turnId,
                            ConversationSpeechArtifactStore::KIND_GUIDANCE,
                        )
                        : null,
                    guidanceSpeechStatus: $guidanceSpeech?->playbackAvailable === true ? 'ready' : ($guidancePending ? 'deferred' : 'unavailable'),
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

            $result = $this->result(
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

            if ($dialogueState !== null) {
                $result = $this->dialogue->complete($dialogueState, $dialogueContext, $modelResult, $result);
                if ($reservationId !== null) {
                    $this->reservations->complete($context->organizationId, $membershipId, $sessionId, $reservationId, $turnId);
                }
            }

            return $result;
        }, $reservationId === null ? 3 : 1);
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
            ->lock(DB::getDriverName() === 'pgsql' ? 'for update of session' : true)
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
     * @return array<string, mixed>
     */
    private function lessonDrillFor(
        string $organizationId,
        string $membershipId,
        string $sessionId,
    ): array {
        if (! Schema::hasTable('conversation_lesson_drills')) {
            return [];
        }

        $columns = [
            'part_id',
            'concept_key',
            'communication_function',
            'talk_scenario',
            'target_patterns',
            'elicitation_goals',
            'fallback_turns',
        ];
        if (Schema::hasColumn('conversation_lesson_drills', 'accepted_pattern_count')) {
            $columns[] = 'accepted_pattern_count';
        }

        $row = DB::table('conversation_lesson_drills')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_session_id', $sessionId)
            ->first($columns);

        if (! $row instanceof stdClass) {
            return [];
        }

        $completedTurns = (int) DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('conversation_session_id', $sessionId)
            ->where('status', 'completed')
            ->count();
        $acceptedPatternCount = 0;
        if (
            Schema::hasColumn('conversation_lesson_drills', 'accepted_pattern_count')
        ) {
            $acceptedPatternCount = max(
                0,
                (int) ($row->accepted_pattern_count ?? 0),
            );
        }

        return [
            'part_id' => is_string($row->part_id ?? null)
                ? $row->part_id
                : '',
            'concept_key' => is_string($row->concept_key ?? null)
                ? $row->concept_key
                : '',
            'accepted_pattern_count' => $acceptedPatternCount,
            'completed_turns' => $completedTurns,
            'communication_function' => is_string($row->communication_function ?? null)
                ? $row->communication_function
                : '',
            'talk_scenario' => is_string($row->talk_scenario ?? null)
                ? $row->talk_scenario
                : '',
            'target_patterns' => $this->decodeStringList($row->target_patterns ?? null),
            'elicitation_goals' => $this->decodeStringList($row->elicitation_goals ?? null),
            'fallback_turns' => $this->decodeStringList($row->fallback_turns ?? null),
        ];
    }

    private function recordAcceptedLessonDrillPattern(
        string $organizationId,
        string $membershipId,
        string $sessionId,
    ): void {
        if (! Schema::hasColumn('conversation_lesson_drills', 'accepted_pattern_count')) {
            return;
        }

        DB::table('conversation_lesson_drills')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_session_id', $sessionId)
            ->increment('accepted_pattern_count');
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(mixed $value): array
    {
        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value) && $value !== '') {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }
        } else {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (! is_string($item) || trim($item) === '') {
                continue;
            }
            $items[] = trim($item);
        }

        return $items;
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
        int $attemptOffset = 0,
        ?int $attemptLimit = null,
    ): ProviderResult {
        $maximumAttempts = max(
            1,
            $attemptLimit ?? (int) config('conversation.maximum_provider_attempts', 2),
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
                    attempt: $attempt + $attemptOffset,
                    usage: $result->usage(),
                    resultStatus: 'succeeded',
                    safeFailureCode: null,
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
                    attempt: $attempt + $attemptOffset,
                    usage: $usage,
                    resultStatus: 'failed',
                    safeFailureCode: $exception->safeCode,
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
        ?string $safeFailureCode,
    ): void {
        $ledgerId = (string) Str::ulid();
        $recordedAt = Date::now()->toImmutable();
        DB::table('ai_cost_ledger')->insert([
            'id' => $ledgerId,
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
            'input_unit_kind' => $usage->inputUnitKind,
            'output_unit_kind' => $usage->outputUnitKind,
            'estimated_cost_microunits' => $usage
                ->estimatedCostMicrounits,
            'result_status' => $resultStatus,
            'recorded_at' => $recordedAt,
        ]);

        $this->observations->recordAiLedger(
            organizationId: $organizationId,
            ledgerId: $ledgerId,
            sessionId: $sessionId,
            stage: $stage,
            semanticOperation: match ($stage) {
                'stt' => 'conversation.transcribe',
                'model' => 'conversation.reply',
                'tts' => 'conversation.synthesize',
                default => 'conversation.unknown',
            },
            routeRole: 'primary',
            resultStatus: $resultStatus,
            safeFailureCode: $safeFailureCode,
            usage: $usage,
            recordedAt: $recordedAt,
        );
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
        $lessonDrill = $this->lessonDrillFor(
            $organizationId,
            $membershipId,
            $this->string($session, 'id'),
        );
        $grammarDrill = $lessonDrill !== [];
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
            lessonDrill: $lessonDrill,
        );
        $uiLocale = $this->string($session, 'ui_locale');
        $storedModel = $this->speechArtifacts->find(
            $organizationId,
            $membershipId,
            $turnId,
            ConversationSpeechArtifactStore::KIND_MODEL,
        );
        if ($storedModel === null) {
            $textToSpeechResult = $this->synthesizeSpeech(
                turnId: $turnId,
                text: $modelResult->assistantReply,
                purpose: GuidanceTtsPolicy::PURPOSE_MODEL_EN,
                uiLocale: $uiLocale,
                grammarDrill: $grammarDrill,
            );
            $this->speechArtifacts->persist(
                $organizationId,
                $membershipId,
                $turnId,
                ConversationSpeechArtifactStore::KIND_MODEL,
                $textToSpeechResult,
            );
        } else {
            $textToSpeechResult = new TextToSpeechResult(
                playbackAvailable: true,
                renderingMode: 'azure-soft-launch-guidance-wav',
                providerUsage: new ProviderUsage(
                    providerKey: 'replayed-speech-artifact',
                    modelVersion: 'replay',
                    latencyMilliseconds: 0,
                    inputUnits: 0,
                    outputUnits: 0,
                    estimatedCostMicrounits: 0,
                ),
                mimeType: (string) $storedModel->mime_type,
            );
        }
        $guidanceText = $modelResult->priorityCorrection?->explanationL1;
        $guidancePending = is_string($guidanceText) && trim($guidanceText) !== '';
        $storedGuidance = $guidancePending
            ? $this->speechArtifacts->find(
                $organizationId,
                $membershipId,
                $turnId,
                ConversationSpeechArtifactStore::KIND_GUIDANCE,
            )
            : null;
        if ($guidancePending && $storedGuidance === null) {
            $this->deferGuidance(
                organizationId: $organizationId,
                membershipId: $membershipId,
                sessionId: $this->string($session, 'id'),
                turnId: $turnId,
                guidanceText: $guidanceText,
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
            speech: $this->speechPayloadBuilder->build(
                modelSpeech: $textToSpeechResult,
                guidanceSpeech: null,
                modelText: $modelResult->assistantReply,
                guidanceText: $guidanceText,
                modelAudioStreamPath: $storedModel !== null
                    || $textToSpeechResult->playbackAvailable
                    ? $this->speechArtifacts->streamPath(
                        $turnId,
                        ConversationSpeechArtifactStore::KIND_MODEL,
                    )
                    : null,
                guidanceAudioStreamPath: $guidancePending
                    ? $this->speechArtifacts->streamPath(
                        $turnId,
                        ConversationSpeechArtifactStore::KIND_GUIDANCE,
                    )
                    : null,
                guidanceSpeechStatus: $storedGuidance !== null
                    ? 'ready'
                    : ($guidancePending ? 'deferred' : 'unavailable'),
            ),
            replayed: $replayed,
        );
    }

    /**
     * @param  ConversationSpeechPayload|null  $speech
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

    private function deferGuidance(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $guidanceText,
        string $uiLocale,
    ): void {
        dispatch(new SynthesizeDeferredGuidanceSpeech(
            $organizationId,
            $membershipId,
            $sessionId,
            $turnId,
            $guidanceText,
            $uiLocale,
        ))->afterResponse();
    }

    private function synthesizeSpeech(
        string $turnId,
        string $text,
        string $purpose,
        string $uiLocale,
        bool $grammarDrill = false,
    ): TextToSpeechResult {
        return $this->textToSpeech->synthesize(new TextToSpeechRequest(
            interactionId: $turnId,
            text: SpokenGrammarNotation::forSpeech(
                $text,
                $grammarDrill && $purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN,
            ),
            voice: GuidanceTtsPolicy::voiceFor($purpose, $uiLocale),
            purpose: $purpose,
            uiLocale: $uiLocale,
        ));
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
