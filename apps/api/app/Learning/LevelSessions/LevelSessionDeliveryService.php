<?php

declare(strict_types=1);

namespace App\Learning\LevelSessions;

use App\Conversation\ConversationSessionService;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Entitlements\EntitlementAccess;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Models\LessonPracticeBlueprint;
use App\Models\LessonPracticeMedia;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LevelSessionDeliveryService
{
    public function __construct(
        private readonly ConversationSessionService $conversations,
        private readonly EntitlementAccess $entitlements,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listLevels(OrganizationContext $context, User $actor): array
    {
        $count = (int) config('level_sessions.level_count', 17);
        $concepts = config('level_sessions.level_concepts', []);
        $parts = config('level_sessions.session_part_ids', []);
        $levels = [];
        $snapshot = $this->entitlementSnapshot($actor);

        for ($number = 1; $number <= $count; $number++) {
            $concept = $concepts[$number] ?? null;
            $blueprint = is_string($concept)
                ? $this->findAttachedBlueprint($context->organizationId, $concept)
                : null;
            $availability = $blueprint === null
                ? 'commissioning_pending'
                : 'available';

            $levels[] = [
                'level_number' => $number,
                'concept_key' => $concept,
                'availability' => $availability,
                'blueprint_id' => $blueprint?->getKey(),
                'session_part_ids' => $parts,
                'entitlement_access' => $this->entitlements->levelAccess($snapshot, $number),
            ];
        }

        return $levels;
    }

    /**
     * @return array<string, mixed>
     */
    public function listSessions(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
    ): array {
        $blueprint = $this->requireAvailableBlueprint($context, $learner, $levelNumber);
        $completions = DB::table('level_session_completions')
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $learner->getKey())
            ->where('level_number', $levelNumber)
            ->pluck('part_id')
            ->all();

        $media = LessonPracticeMedia::query()
            ->where('organization_id', $context->organizationId)
            ->where('blueprint_id', $blueprint->getKey())
            ->orderBy('part_order')
            ->get();

        $sessions = [];
        foreach ($media as $row) {
            $sessions[] = [
                'part_id' => $row->part_id,
                'part_order' => $row->part_order,
                'media_asset_id' => $row->media_asset_id,
                'completed' => in_array($row->part_id, $completions, true),
            ];
        }

        return [
            'level_number' => $levelNumber,
            'concept_key' => $blueprint->concept_key,
            'blueprint_id' => $blueprint->getKey(),
            'blueprint_content_hash' => $blueprint->content_hash,
            'sessions' => $sessions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionDetail(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        string $partId,
    ): array {
        $blueprint = $this->requireAvailableBlueprint($context, $learner, $levelNumber);
        $this->assertPartId($partId);
        $media = LessonPracticeMedia::query()
            ->where('organization_id', $context->organizationId)
            ->where('blueprint_id', $blueprint->getKey())
            ->where('part_id', $partId)
            ->first();
        if ($media === null) {
            throw new LearningOperationRejected('session_missing');
        }

        $canonical = $blueprint->canonical_json;
        $lesson = is_array($canonical['lesson'] ?? null) ? $canonical['lesson'] : [];
        $check = is_array($canonical['check'] ?? null) ? $canonical['check'] : [];
        $talk = is_array($canonical['talk'] ?? null) ? $canonical['talk'] : [];
        $apply = is_array($canonical['apply'] ?? null) ? $canonical['apply'] : [];
        $kannada = is_array($canonical['kannada_guidance'] ?? null)
            ? $canonical['kannada_guidance']
            : [
                'summary' => 'ಈ ಪಾಠದ ಆಡಿಯೋವನ್ನು ಕೇಳಿ. ಗುರಿ ವಿನ್ಯಾಸವನ್ನು ಅರ್ಥಮಾಡಿಕೊಳ್ಳಿ.',
            ];

        $checkItems = [];
        foreach ($check['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $checkItems[] = [
                'id' => $item['id'] ?? null,
                'weight' => $item['weight'] ?? null,
                'prompt' => $item['prompt'] ?? null,
                'options' => $item['options'] ?? [],
            ];
        }

        $completed = DB::table('level_session_completions')
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $learner->getKey())
            ->where('level_number', $levelNumber)
            ->where('part_id', $partId)
            ->exists();

        return [
            'level_number' => $levelNumber,
            'part_id' => $partId,
            'part_order' => $media->part_order,
            'completed' => $completed,
            'media_asset_id' => $media->media_asset_id,
            'audio_stream_path' => sprintf(
                '/api/v1/me/levels/%d/sessions/%s/audio',
                $levelNumber,
                $partId,
            ),
            'objective' => $lesson['objective'] ?? null,
            'target_patterns' => $lesson['target_patterns'] ?? [],
            'kannada_guidance' => $kannada,
            'talk' => $partId === 'conversation' || $partId === 'introduction'
                ? [
                    'scenario' => $talk['scenario'] ?? null,
                    'elicitation_goals' => $talk['elicitation_goals'] ?? [],
                    'fallback_turns' => $talk['fallback_turns'] ?? [],
                    'free_speech' => true,
                ]
                : null,
            'apply' => $partId === 'interaction'
                ? [
                    'task' => $apply['task'] ?? null,
                    'rubric' => $apply['rubric'] ?? [],
                    'free_speech' => true,
                ]
                : null,
            'check' => $partId === 'practice'
                ? [
                    'pass_threshold' => $check['pass_threshold'] ?? 80,
                    'items' => $checkItems,
                ]
                : null,
            'activities' => $this->activitiesFor($partId, $lesson, $talk),
            'conversation_binding' => config(
                'level_sessions.conversation_bindings.'.$partId,
            ),
        ];
    }

    /**
     * @param  array<string, int>  $answers  item id => option index
     * @return array<string, mixed>
     */
    public function submitCheck(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        string $partId,
        array $answers,
        string $idempotencyKey,
    ): array {
        if ($partId !== 'practice') {
            throw new LearningOperationRejected('check_not_applicable');
        }
        $this->assertPartUnlocked($context, $learner, $levelNumber, $partId);

        $blueprint = $this->requireAvailableBlueprint($context, $learner, $levelNumber);
        $canonical = $blueprint->canonical_json;
        $check = is_array($canonical['check'] ?? null) ? $canonical['check'] : [];
        $items = is_array($check['items'] ?? null) ? $check['items'] : [];
        $threshold = (int) ($check['pass_threshold'] ?? 80);

        return DB::transaction(function () use (
            $context,
            $learner,
            $levelNumber,
            $partId,
            $answers,
            $idempotencyKey,
            $blueprint,
            $items,
            $threshold,
        ): array {
            $existing = DB::table('level_session_check_submissions')
                ->where('organization_id', $context->organizationId)
                ->where('user_id', $learner->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return [
                    'replayed' => true,
                    'score' => (int) $existing->score,
                    'pass_threshold' => (int) $existing->pass_threshold,
                    'passed' => (bool) $existing->passed,
                    'item_results' => json_decode(
                        (string) $existing->item_results_json,
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    ),
                ];
            }

            $earned = 0;
            $total = 0;
            $results = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = (string) ($item['id'] ?? '');
                $weight = (int) ($item['weight'] ?? 0);
                $correct = (int) ($item['correct_option'] ?? -1);
                $selected = array_key_exists($id, $answers)
                    ? (int) $answers[$id]
                    : -1;
                $isCorrect = $selected === $correct;
                if ($isCorrect) {
                    $earned += $weight;
                }
                $total += $weight;
                $results[] = [
                    'id' => $id,
                    'selected_option' => $selected,
                    'correct' => $isCorrect,
                    'rationale' => $item['rationale'] ?? null,
                ];
            }

            $score = $total > 0 ? (int) round(($earned / $total) * 100) : 0;
            $passed = $score >= $threshold;
            $id = (string) Str::ulid();
            DB::table('level_session_check_submissions')->insert([
                'id' => $id,
                'organization_id' => $context->organizationId,
                'user_id' => $learner->getKey(),
                'membership_id' => $context->membershipId,
                'level_number' => $levelNumber,
                'part_id' => $partId,
                'blueprint_id' => $blueprint->getKey(),
                'blueprint_content_hash' => $blueprint->content_hash,
                'score' => $score,
                'pass_threshold' => $threshold,
                'passed' => $passed,
                'item_results_json' => json_encode($results, JSON_THROW_ON_ERROR),
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'replayed' => false,
                'score' => $score,
                'pass_threshold' => $threshold,
                'passed' => $passed,
                'item_results' => $results,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function completeSession(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        string $partId,
        string $idempotencyKey,
    ): array {
        $this->assertPartId($partId);
        $this->assertPartUnlocked($context, $learner, $levelNumber, $partId);
        $this->assertPracticeCheckPassed($context, $learner, $levelNumber, $partId);
        $blueprint = $this->requireAvailableBlueprint($context, $learner, $levelNumber);
        $media = LessonPracticeMedia::query()
            ->where('organization_id', $context->organizationId)
            ->where('blueprint_id', $blueprint->getKey())
            ->where('part_id', $partId)
            ->first();
        if ($media === null) {
            throw new LearningOperationRejected('session_missing');
        }

        return DB::transaction(function () use (
            $context,
            $learner,
            $levelNumber,
            $partId,
            $idempotencyKey,
            $blueprint,
        ): array {
            $existing = DB::table('level_session_completions')
                ->where('organization_id', $context->organizationId)
                ->where('user_id', $learner->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return [
                    'replayed' => true,
                    'completion_id' => $existing->id,
                    'part_id' => $existing->part_id,
                ];
            }

            $id = (string) Str::ulid();
            DB::table('level_session_completions')->insert([
                'id' => $id,
                'organization_id' => $context->organizationId,
                'user_id' => $learner->getKey(),
                'membership_id' => $context->membershipId,
                'level_number' => $levelNumber,
                'part_id' => $partId,
                'blueprint_id' => $blueprint->getKey(),
                'blueprint_content_hash' => $blueprint->content_hash,
                'completion_kind' => 'participation',
                'result_json' => null,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'replayed' => false,
                'completion_id' => $id,
                'part_id' => $partId,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function startBoundConversation(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        string $partId,
        string $uiLocale,
        string $idempotencyKey,
    ): array {
        $this->assertPartId($partId);
        $this->assertPartUnlocked($context, $learner, $levelNumber, $partId);
        $this->requireAvailableBlueprint($context, $learner, $levelNumber);
        $binding = config('level_sessions.conversation_bindings.'.$partId);
        if (! is_array($binding)) {
            throw new LearningOperationRejected('conversation_unbound');
        }
        $prototypeKey = (string) ($binding['prototype'] ?? 'friend');
        $prototype = config('conversation.prototypes.'.$prototypeKey);
        if (! is_array($prototype)) {
            throw new LearningOperationRejected('prototype_missing');
        }

        try {
            return $this->conversations->start(
                learner: $learner,
                personaKey: (string) $prototype['persona_key'],
                scenarioKey: (string) $prototype['scenario_key'],
                coachingMode: (string) ($binding['coaching_mode'] ?? 'gentle'),
                uiLocale: $uiLocale,
                idempotencyKey: $idempotencyKey,
            );
        } catch (ConversationIdempotencyConflict $exception) {
            throw $exception;
        } catch (ConversationOperationRejected) {
            throw new LearningOperationRejected('conversation_rejected');
        }
    }

    private function requireAvailableBlueprint(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
    ): LessonPracticeBlueprint {
        $this->entitlements->assertLevel($learner, $levelNumber);
        $concepts = config('level_sessions.level_concepts', []);
        $concept = $concepts[$levelNumber] ?? null;
        if (! is_string($concept)) {
            throw new LearningOperationRejected('level_commissioning_pending');
        }
        $blueprint = $this->findAttachedBlueprint(
            $context->organizationId,
            $concept,
        );
        if ($blueprint === null) {
            throw new LearningOperationRejected('level_commissioning_pending');
        }

        return $blueprint;
    }

    private function findAttachedBlueprint(
        string $organizationId,
        string $conceptKey,
    ): ?LessonPracticeBlueprint {
        $blueprint = LessonPracticeBlueprint::query()
            ->where('organization_id', $organizationId)
            ->where('concept_key', $conceptKey)
            ->whereIn('state', ['draft', 'published'])
            ->orderByDesc('revision_number')
            ->first();
        if ($blueprint === null) {
            return null;
        }

        $mediaCount = LessonPracticeMedia::query()
            ->where('organization_id', $organizationId)
            ->where('blueprint_id', $blueprint->getKey())
            ->count();

        return $mediaCount === 4 ? $blueprint : null;
    }

    private function assertPartUnlocked(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        string $partId,
    ): void {
        $order = config('level_sessions.session_part_ids', []);
        if (! is_array($order)) {
            return;
        }
        $index = array_search($partId, $order, true);
        if (! is_int($index) || $index <= 0) {
            return;
        }
        $previous = $order[$index - 1] ?? null;
        if (! is_string($previous)) {
            return;
        }
        $completed = DB::table('level_session_completions')
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $learner->getKey())
            ->where('level_number', $levelNumber)
            ->where('part_id', $previous)
            ->exists();
        if (! $completed) {
            throw new LearningOperationRejected('session_locked');
        }
    }

    private function assertPracticeCheckPassed(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        string $partId,
    ): void {
        if ($partId !== 'practice') {
            return;
        }
        $passed = DB::table('level_session_check_submissions')
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $learner->getKey())
            ->where('level_number', $levelNumber)
            ->where('part_id', 'practice')
            ->where('passed', true)
            ->exists();
        if (! $passed) {
            throw new LearningOperationRejected('practice_check_required');
        }
    }

    private function assertPartId(string $partId): void
    {
        $allowed = config('level_sessions.session_part_ids', []);
        if (! in_array($partId, $allowed, true)) {
            throw new LearningOperationRejected('part_invalid');
        }
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @param  array<string, mixed>  $talk
     * @return list<array{id: string, prompt: string}>
     */
    private function activitiesFor(string $partId, array $lesson, array $talk): array
    {
        $patterns = is_array($lesson['target_patterns'] ?? null)
            ? $lesson['target_patterns']
            : [];

        return match ($partId) {
            'introduction' => [
                ['id' => 'listen', 'prompt' => 'Listen to the Introduction audio once for meaning.'],
                ['id' => 'notice', 'prompt' => 'Notice these patterns: '.implode(' ', array_slice($patterns, 0, 3))],
                ['id' => 'speak', 'prompt' => 'Say one short sentence using the new pattern.'],
            ],
            'practice' => [
                ['id' => 'rehearse', 'prompt' => 'Repeat two model sentences from the Practice audio.'],
                ['id' => 'transform', 'prompt' => 'Change one affirmative sentence into a question.'],
                ['id' => 'check', 'prompt' => 'Complete the Check items for this level.'],
            ],
            'conversation' => [
                ['id' => 'free_talk', 'prompt' => (string) ($talk['scenario'] ?? 'Talk freely about the level topic.')],
            ],
            'interaction' => [
                ['id' => 'apply', 'prompt' => 'Use the skill in a freer everyday situation.'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementSnapshot(User $actor): array
    {
        try {
            return $this->entitlements->snapshot($actor);
        } catch (LearningOperationRejected) {
            return [
                'applies' => false,
                'max_level_number' => null,
            ];
        }
    }
}
