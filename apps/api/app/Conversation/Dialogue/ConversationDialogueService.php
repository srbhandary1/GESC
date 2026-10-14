<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Support\RoleplayCatalog;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Server-owned interaction state; bodies live only in the approved private recovery store. */
final readonly class ConversationDialogueService
{
    public const string NOTICE = 'conversation-recovery-2026-09-25';

    public function __construct(private ConversationRecoveryStore $recovery) {}

    public function initialize(string $organizationId, string $membershipId, string $sessionId, string $flow, string $mode, string $scenarioKey, string $scenarioVersionId, string $locale, ?LessonQuestionContext $lessonQuestion = null): void
    {
        if (! in_array($flow, ['roleplay_dialogue', 'question_help', 'free_speech'], true)
            || ($flow === 'roleplay_dialogue') !== ($mode === 'roleplay')) {
            throw new ConversationOperationRejected;
        }
        $definition = ['ai_role' => 'Swathi', 'learner_role' => 'Learner', 'opening_reply' => null, 'scenario' => 'Ask an educational question.', 'objectives' => []];
        if ($lessonQuestion !== null) {
            $definition['lesson'] = $lessonQuestion->content;
            $definition['scenario'] = 'Explain and practise only the supplied lesson.';
        }
        if ($flow === 'free_speech') {
            $definition['scenario'] = 'Friendly English conversation on the learner chosen topic, with optional gentle coaching.';
            $definition['opening_reply'] = ['text' => 'Hello! What would you like to talk about today?', 'locale' => 'en-IN'];
        }
        if ($flow === 'roleplay_dialogue') {
            $entry = collect(RoleplayCatalog::entries())->firstWhere('scenario_key', $scenarioKey);
            if ($entry === null || $entry['pedagogy'] === null) {
                throw new ConversationOperationRejected('dialogue_scenario_unavailable');
            }
            $first = $entry['pedagogy']['observe_model'][0] ?? '';
            if (! str_contains($first, ':')) {
                throw new ConversationOperationRejected('dialogue_scenario_unavailable');
            }
            [$role, $opening] = explode(':', $first, 2);
            $definition['ai_role'] = trim($role);
            $definition['learner_role'] = match ($entry['id']) {
                'clinic-reception', 'doctor-appointment' => 'Patient', 'job-interview' => 'Candidate',
                'hotel-checkin' => 'Guest', 'school-parent-meeting' => 'Parent',
                'airport-checkin', 'bus-train-ticket' => 'Passenger', 'office-meeting' => 'Colleague',
                default => 'Customer',
            };
            $definition['opening_reply'] = ['text' => trim($opening), 'locale' => 'en-IN'];
            $definition['scenario'] = $entry['pedagogy']['perform_prompt'];
            $definition['objectives'] = DB::table('scenario_objectives')->where('organization_id', $organizationId)
                ->where('scenario_version_id', $scenarioVersionId)->orderBy('sort_order')->get()
                ->map(fn (stdClass $row): array => ['key' => (string) $row->objective_key, 'title' => (string) $row->title])->all();
        }
        $artifact = $this->recovery->persist($organizationId, $membershipId, $sessionId, null, $definition);
        $now = Date::now()->toImmutable();
        DB::table('conversation_dialogue_states')->insert([
            'organization_id' => $organizationId, 'learner_membership_id' => $membershipId,
            'conversation_session_id' => $sessionId, 'flow' => $flow,
            'state' => $flow === 'question_help' ? 'awaiting_question' : 'awaiting_reply',
            'revision' => 0, 'opportunity' => 1, 'start_artifact_id' => $artifact,
            'notice_version' => self::NOTICE, 'notice_accepted_at' => $now,
            'expires_at' => $now->addMinutes(15), 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function state(string $organizationId, string $membershipId, string $sessionId): ?stdClass
    {
        return DB::table('conversation_dialogue_states')->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId)->first();
    }

    /** @return array<string, mixed> */
    public function present(stdClass $state): array
    {
        $definition = $this->definition($state);
        $status = (string) $state->state;
        if ($status !== 'closed' && $definition === null) {
            $status = 'expired';
        }

        return [
            'flow' => $state->flow, 'state' => $status, 'revision' => (int) $state->revision,
            'opportunity' => (int) $state->opportunity, 'ai_role' => $definition['ai_role'] ?? 'Swathi',
            'learner_role' => $definition['learner_role'] ?? 'Learner',
            'opening_reply' => $definition['opening_reply'] ?? null,
            'recovery_expires_at' => $status === 'expired' || $status === 'closed' ? null : Date::parse($state->expires_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function latest(stdClass $state): ?array
    {
        if (! is_string($state->latest_artifact_id) || $state->state === 'closed' || $this->definition($state) === null) {
            return null;
        }
        $saved = $this->recovery->read((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id, $state->latest_artifact_id);
        $result = $saved['result'] ?? null;

        return is_array($result) ? $result : null;
    }

    /** @return array<string, mixed> */
    public function replay(stdClass $state, string $turnId): array
    {
        $id = DB::table('conversation_dialogue_outcomes')->where('organization_id', $state->organization_id)
            ->where('learner_membership_id', $state->learner_membership_id)->where('conversation_session_id', $state->conversation_session_id)
            ->where('conversation_turn_id', $turnId)->value('artifact_id');
        $saved = is_string($id) && $state->state !== 'closed'
            ? $this->recovery->read((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id, $id) : null;
        if (! is_array($saved['result'] ?? null)) {
            throw new ConversationOperationRejected('conversation_recovery_expired');
        }
        $saved['result']['replayed'] = true;

        return $saved['result'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function prepare(stdClass $state, array $input, string $utterance, string $locale): array
    {
        if (($input['expected_revision'] ?? null) !== (int) $state->revision) {
            throw new ConversationIdempotencyConflict;
        }
        $definition = $this->definition($state);
        if ($definition === null || in_array($state->state, ['complete', 'closed'], true)) {
            throw new ConversationOperationRejected('conversation_recovery_expired');
        }
        $action = $input['action'] ?? '';
        if ($state->flow === 'roleplay_dialogue') {
            $expected = $state->state === 'retry_required' ? 'retry' : 'reply';
            if ($action !== $expected) {
                throw new ConversationOperationRejected('dialogue_action_invalid');
            }
        } elseif ($state->flow === 'free_speech') {
            if (! in_array($action, ['reply', 'accept_explanation'], true)) {
                throw new ConversationOperationRejected('dialogue_action_invalid');
            }
        } else {
            $word = mb_strtolower(trim($utterance, " \n\r\t.!?"));
            $directExplanationRequest = false;
            if ($state->state === 'awaiting_explanation_permission' && in_array($action, ['question', 'reply'], true)) {
                if (
                    in_array($word, ['yes', 'yes please', 'yes explain', 'yes, explain', 'yes explain in my language', 'yes, explain in my language', 'please explain', 'sure', 'ok', 'okay', 'ಹೌದು', 'हाँ', 'അതെ', 'ஆம்', 'అవును'], true)
                    || preg_match('/\b(?:explain|kannada|hindi|tamil|telugu|malayalam|my\s+language)\b|ಕನ್ನಡ|ವಿವರಿಸಿ|समझा/iu', $word) === 1
                ) {
                    $action = 'accept_explanation';
                } elseif (in_array($word, ['no', 'no thanks', 'no keep english', 'no, keep english', 'ಇಲ್ಲ', 'नहीं', 'ഇല്ല', 'இல்லை', 'కాదు'], true)) {
                    $action = 'decline_explanation';
                } else {
                    $action = 'question';
                }
            } elseif (
                $state->state === 'awaiting_question'
                && $action === 'question'
                && is_string($state->original_turn_id)
                && preg_match('/^(?:please\s+)?(?:can\s+you\s+)?(?:explain(?:\s+(?:this|that|it))?(?:\s+to\s+me)?\s+in\s+(?:my\s+language|kannada|hindi|tamil|telugu|malayalam)|in\s+(?:kannada|hindi|tamil|telugu|malayalam)\s+please)$|ಕನ್ನಡದಲ್ಲಿ\s*ವಿವರಿಸಿ|हिंदी\s*में\s*समझा/iu', $word) === 1
            ) {
                $action = 'accept_explanation';
                $directExplanationRequest = true;
            }
            if ($action === 'question' && in_array($word, ["i don't understand", 'i do not understand', 'i did not understand', 'ನನಗೆ ಅರ್ಥವಾಗಲಿಲ್ಲ', 'समझ नहीं आया'], true)) {
                $action = 'confused';
            }
            if (! in_array($action, ['question', 'confused', 'accept_explanation', 'decline_explanation'], true)
                || ($action === 'decline_explanation' && $state->state !== 'awaiting_explanation_permission')
                || ($action === 'accept_explanation' && $state->state !== 'awaiting_explanation_permission' && ! $directExplanationRequest)
                || ($action === 'confused' && $state->latest_turn_id === null)) {
                throw new ConversationOperationRejected('dialogue_action_invalid');
            }
        }
        if (array_key_exists('explanation_target', $input) && ($state->flow !== 'free_speech' || $action !== 'accept_explanation')) {
            throw new ConversationOperationRejected('dialogue_action_invalid');
        }
        // Speech provenance is established from a scoped recording, never from a client assertion.
        $source = 'typed';
        $transcriptLocale = 'unknown';
        $recordingId = $input['voice_recording_id'] ?? null;
        if (is_string($recordingId)) {
            $recording = DB::table('voice_recordings')->where('organization_id', $state->organization_id)
                ->where('learner_membership_id', $state->learner_membership_id)->where('conversation_session_id', $state->conversation_session_id)
                ->where('id', $recordingId)->whereIn('status', ['confirmed', 'corrected'])->first();
            if (! $recording instanceof stdClass || ! hash_equals((string) $recording->confirmed_transcript_sha256, hash('sha256', $utterance))) {
                throw new ConversationOperationRejected('dialogue_transcript_unverified');
            }
            $source = (string) ($recording->transcript_source ?? 'translated_speech');
            $transcriptLocale = (string) ($recording->transcript_locale ?? 'unknown');
            if ($source !== 'original_speech' && $state->flow === 'roleplay_dialogue') {
                throw new ConversationOperationRejected('original_transcript_required');
            }
        }
        $history = [];
        $rows = DB::table('conversation_dialogue_outcomes')->where('organization_id', $state->organization_id)
            ->where('learner_membership_id', $state->learner_membership_id)->where('conversation_session_id', $state->conversation_session_id)
            ->orderByDesc('revision')->limit(8)->get();
        foreach ($rows->reverse() as $row) {
            $saved = $this->recovery->read((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id, (string) $row->artifact_id);
            if (is_array($saved['context_turn'] ?? null) && ! ($state->flow === 'free_speech' && ($saved['context_turn']['action'] ?? '') === 'accept_explanation')) {
                $history[] = $saved['context_turn'];
            }
        }
        $partner = $definition['opening_reply']['text'] ?? '';
        if (is_string($state->partner_turn_id)) {
            $partnerTurn = $this->replay($state, $state->partner_turn_id);
            $partner = $partnerTurn['reply']['text'] ?? '';
        }

        $explanationTarget = null;
        if ($state->flow === 'question_help' && $action === 'accept_explanation' && is_string($state->original_turn_id)) {
            $question = $this->replay($state, $state->original_turn_id);
            $explanationTarget = ['question' => $question['transcript'] ?? '', 'answer' => $question['reply'] ?? null];
        }

        if ($state->flow === 'free_speech' && $action === 'accept_explanation') {
            $target = $input['explanation_target'] ?? null;
            if (! is_string($target)) {
                throw new ConversationOperationRejected('dialogue_action_invalid');
            }
            $sourceReply = $target === 'opening' ? ['reply' => $definition['opening_reply'] ?? null]
                : $this->replay($state, $target);
            if (! is_array($sourceReply['reply'] ?? null) || ($sourceReply['reply']['locale'] ?? '') !== 'en-IN'
                || ($target !== 'opening' && (($sourceReply['status'] ?? '') !== 'completed' || ($sourceReply['safety_state'] ?? '') !== 'ok'))) {
                throw new ConversationOperationRejected('dialogue_action_invalid');
            }
            $explanationTarget = ['question' => $sourceReply['transcript'] ?? '', 'answer' => $sourceReply['reply']];
        }

        $requestedReplyLocale = $action === 'accept_explanation'
            ? $this->resolveExplanationLocale($utterance, $locale)
            : 'en-IN';

        return [...$definition, 'flow' => $state->flow, 'action' => $action, 'ui_locale' => $locale,
            'explanation_target' => $explanationTarget,
            'explanation_target_id' => $state->flow === 'free_speech' && $action === 'accept_explanation' ? $input['explanation_target'] : null,
            'requested_reply_locale' => $requestedReplyLocale,
            'current_partner_line' => $partner, 'history' => $history, 'source' => $source,
            'transcript_locale' => $transcriptLocale,
            'objective_progress' => $this->feedback($state), 'opportunity' => (int) $state->opportunity];
    }

    private function resolveExplanationLocale(string $utterance, string $sessionLocale): string
    {
        $lower = mb_strtolower($utterance);
        if (str_contains($lower, 'hindi') || str_contains($lower, 'हिंदी') || str_contains($lower, 'हिन्दी')) {
            return 'hi-IN';
        }
        if (str_contains($lower, 'tamil') || str_contains($lower, 'தமிழ்')) {
            return 'ta-IN';
        }
        if (str_contains($lower, 'telugu') || str_contains($lower, 'తెలుగు')) {
            return 'te-IN';
        }
        if (str_contains($lower, 'malayalam') || str_contains($lower, 'മലയാളം')) {
            return 'ml-IN';
        }
        if (str_contains($lower, 'kannada') || str_contains($lower, 'ಕನ್ನಡ')) {
            return 'kn-IN';
        }

        return $sessionLocale !== 'en-IN' ? $sessionLocale : 'kn-IN';
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function complete(stdClass $state, array $context, ?ConversationModelResult $model, array $result): array
    {
        $usable = $result['status'] === 'completed' && $model !== null
            && $model->safetyState === 'ok' && ! $model->usedFallback;
        $assessment = $usable ? ($model->dialogue['assessment'] ?? 'unassessed') : 'unassessed';
        $assessment = is_string($assessment) ? $assessment : 'unassessed';
        $action = (string) $context['action'];
        $next = (string) $state->state;
        $opportunity = (int) $state->opportunity;
        $original = $state->original_turn_id;
        $partnerTurnId = $state->partner_turn_id;
        $evidence = [];
        if ($usable) {
            if ($state->flow === 'roleplay_dialogue') {
                if ($assessment === 'accepted') {
                    $evidence = $model->dialogue['objective_evidence'] ?? [];
                    $next = 'awaiting_reply';
                    if (($model->dialogue['complete'] ?? false) === true && is_array($evidence)) {
                        $progress = [];
                        foreach ($this->feedback($state) as $entry) {
                            $progress[$entry['objective_key']] = $entry['completion_state'];
                        }
                        foreach ($evidence as $entry) {
                            if (is_array($entry)) {
                                $progress[$entry['objective_key']] = $entry['state'];
                            }
                        }
                        if ($progress !== [] && count(array_filter($progress, fn ($value): bool => $value !== 'met')) === 0) {
                            $next = 'complete';
                        }
                    }
                    $opportunity++;
                    $partnerTurnId = $result['id'];
                    $original = null;
                } elseif ($assessment === 'retry_required') {
                    $next = 'retry_required';
                    $original ??= $result['id'];
                }
            } elseif ($state->flow === 'free_speech') {
                $next = 'awaiting_reply';
                if ($action !== 'accept_explanation') {
                    $partnerTurnId = $result['id'];
                    $opportunity++;
                }
            } else {
                $next = $action === 'confused' || $model->understoodIntent === 'help_confused'
                    ? 'awaiting_explanation_permission' : 'awaiting_question';
                if ($action === 'question' && $model->understoodIntent !== 'help_confused') {
                    $original = $result['id'];
                }
            }
        }
        $revision = (int) $state->revision + 1;
        DB::table('conversation_dialogue_states')->where('conversation_session_id', $state->conversation_session_id)
            ->where('organization_id', $state->organization_id)->update([
                'state' => $next, 'revision' => $revision, 'opportunity' => $opportunity,
                'original_turn_id' => $original, 'latest_turn_id' => $result['id'], 'updated_at' => Date::now(),
                'partner_turn_id' => $partnerTurnId,
            ]);
        $fresh = $this->state((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id);
        if ($fresh === null) {
            throw new ConversationOperationRejected;
        }
        $result['reply'] = $model !== null && $result['status'] === 'completed'
            ? ['text' => $model->assistantReply, 'locale' => $model->replyLocale] : null;
        $result['assistant_reply'] = $model?->replyLocale === 'en-IN' ? $result['assistant_reply'] : null;
        $result['assessment'] = $assessment;
        if ($state->flow === 'free_speech' && $action === 'accept_explanation') {
            $result['explanation_target'] = $context['explanation_target_id'];
            $result['explanation_source'] = $context['explanation_target']['answer'];
        }
        $result['original_turn_id'] = $original;
        $result['interaction'] = $this->present($fresh);
        $payload = ['result' => $result, 'context_turn' => [
            'id' => $result['id'],
            'learner' => $result['transcript'], 'reply' => $result['reply'],
            'assessment' => $assessment, 'action' => $action,
        ]];
        $artifact = $this->recovery->persist((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id, (string) $result['id'], $payload);
        DB::table('conversation_dialogue_outcomes')->insert([
            'conversation_turn_id' => $result['id'], 'organization_id' => $state->organization_id,
            'learner_membership_id' => $state->learner_membership_id, 'conversation_session_id' => $state->conversation_session_id,
            'artifact_id' => $artifact, 'revision' => $revision, 'opportunity' => $state->opportunity,
            'assessment' => $assessment, 'action' => $action,
            'objective_evidence' => json_encode($evidence, JSON_THROW_ON_ERROR), 'created_at' => Date::now(),
        ]);
        DB::table('conversation_dialogue_states')->where('conversation_session_id', $state->conversation_session_id)
            ->where('organization_id', $state->organization_id)->update(['latest_artifact_id' => $artifact]);

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function feedback(stdClass $state): array
    {
        $session = DB::table('conversation_sessions')->where('organization_id', $state->organization_id)->where('id', $state->conversation_session_id)->first();
        if (! $session instanceof stdClass) {
            throw new ConversationOperationRejected;
        }
        $outcomes = DB::table('conversation_dialogue_outcomes')->where('organization_id', $state->organization_id)
            ->where('learner_membership_id', $state->learner_membership_id)->where('conversation_session_id', $state->conversation_session_id)
            ->where('assessment', 'accepted')->orderBy('revision')->get();
        $evidence = [];
        foreach ($outcomes as $outcome) {
            $entries = json_decode((string) $outcome->objective_evidence, true, 32, JSON_THROW_ON_ERROR);
            foreach (is_array($entries) ? $entries : [] as $entry) {
                if (is_array($entry) && is_string($entry['objective_key'] ?? null)) {
                    $evidence[$entry['objective_key']] = ['completion_state' => $entry['state'], 'source_turn_id' => $outcome->conversation_turn_id];
                }
            }
        }

        return array_values(DB::table('scenario_objectives')->where('organization_id', $state->organization_id)
            ->where('scenario_version_id', $session->scenario_version_id)->orderBy('sort_order')->get()->map(function (stdClass $objective) use ($evidence): array {
                $row = $evidence[(string) $objective->objective_key] ?? ['completion_state' => 'unassessed', 'source_turn_id' => null];

                return ['id' => $objective->id, 'scenario_objective_id' => $objective->id, 'objective_key' => $objective->objective_key,
                    ...$row, 'advisory' => true, 'evidence_digest' => hash('sha256', (string) $objective->id.'|'.($row['source_turn_id'] ?? 'unassessed'))];
            })->all());
    }

    public function close(stdClass $state): void
    {
        DB::transaction(function () use ($state): void {
            DB::table('conversation_sessions')->where('organization_id', $state->organization_id)
                ->where('learner_membership_id', $state->learner_membership_id)->where('id', $state->conversation_session_id)->lockForUpdate()->first();
            DB::table('conversation_dialogue_states')->where('organization_id', $state->organization_id)
                ->where('conversation_session_id', $state->conversation_session_id)->update(['state' => 'closed', 'closed_at' => Date::now()]);
        });
        $this->recovery->deleteSession((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id);
    }

    /** @return array<string, mixed>|null */
    private function definition(stdClass $state): ?array
    {
        if ($state->state === 'closed' || Date::parse($state->expires_at)->isPast()) {
            return null;
        }

        return $this->recovery->read((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id, (string) $state->start_artifact_id);
    }
}
