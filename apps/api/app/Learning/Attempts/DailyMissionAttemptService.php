<?php

declare(strict_types=1);

namespace App\Learning\Attempts;

use App\Audit\AuditEventRecorder;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\LearningCommandIdempotency;
use App\Learning\Missions\DailyMissionDefinition;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Policy\DailySpeakingPolicy;
use App\Learning\Policy\DailySpeakingPolicyRegistry;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class DailyMissionAttemptService
{
    public function __construct(
        private LearnerAuthority $authority,
        private LearningCommandIdempotency $commands,
        private DailyMissionReader $missions,
        private DailySpeakingPolicyRegistry $policies,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    /**
     * @return array{id: string, status: string, replayed: bool}
     */
    public function start(
        User $learner,
        string $enrollmentId,
        string $missionId,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): array {
        $this->assertUlid($enrollmentId);
        $this->assertUlid($missionId);
        $this->commands->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $learner,
            $enrollmentId,
            $missionId,
            $idempotencyKey,
            $correlationId,
        ): array {
            $context = $this->authority->require($learner);
            $fingerprint = $this->commands->fingerprint([
                'enrollment_id' => $enrollmentId,
                'mission_id' => $missionId,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'learner.attempt.start',
                aggregateType: 'enrollment',
                aggregateId: $enrollmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                $attemptId = $this->objectString($replay, 'result_id');
                $attempt = $this->attempt(
                    $context->organizationId,
                    (string) $context->membershipId,
                    $attemptId,
                    lock: false,
                );
                $this->policyForAttempt($attempt);

                if (
                    $this->objectString($attempt, 'enrollment_id')
                        !== $enrollmentId
                    || $this->objectString(
                        $attempt,
                        'curriculum_mission_id',
                    ) !== $missionId
                ) {
                    throw new LearningOperationRejected;
                }

                return [
                    'id' => $this->objectString($attempt, 'id'),
                    'status' => 'in_progress',
                    'replayed' => true,
                ];
            }

            $mission = $this->missions->resolve(
                $learner,
                $enrollmentId,
                $missionId,
            );
            $open = DB::table('learner_attempts')
                ->where('organization_id', $context->organizationId)
                ->where('enrollment_id', $enrollmentId)
                ->where('learner_membership_id', $context->membershipId)
                ->where('curriculum_mission_id', $missionId)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();

            if ($open instanceof stdClass) {
                $this->policyForAttempt($open);
                $this->assertAttemptMatchesMission($open, $mission);
                $attemptId = $this->objectString($open, 'id');
                $now = Date::now()->toImmutable();
                $this->commands->record(
                    actor: $learner,
                    operation: 'learner.attempt.start',
                    aggregateType: 'enrollment',
                    aggregateId: $enrollmentId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                    resultType: 'learner_attempt',
                    resultId: $attemptId,
                    completedAt: $now,
                );

                return [
                    'id' => $attemptId,
                    'status' => 'in_progress',
                    'replayed' => true,
                ];
            }

            $policy = $this->policies->default();
            $attemptNumber = (int) DB::table('learner_attempts')
                ->where('organization_id', $context->organizationId)
                ->where('enrollment_id', $enrollmentId)
                ->where('curriculum_mission_id', $missionId)
                ->max('attempt_number') + 1;
            $now = Date::now()->toImmutable();
            $attemptId = (string) Str::ulid();
            DB::table('learner_attempts')->insert([
                'id' => $attemptId,
                'organization_id' => $context->organizationId,
                'enrollment_id' => $enrollmentId,
                'learner_membership_id' => $context->membershipId,
                'curriculum_owner_organization_id' => $mission
                    ->ownerOrganizationId,
                'curriculum_version_id' => $mission->curriculumVersionId,
                'curriculum_publication_id' => $mission->publicationId,
                'curriculum_mission_id' => $mission->missionId,
                'learning_objective_id' => $mission->objectiveId,
                'publication_manifest_hash' => $mission->manifestHash,
                'mission_definition_hash' => $mission->definitionHash,
                'rule_version' => $policy->version,
                'attempt_number' => $attemptNumber,
                'status' => 'in_progress',
                'started_at' => $now,
                'submitted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->commands->record(
                actor: $learner,
                operation: 'learner.attempt.start',
                aggregateType: 'enrollment',
                aggregateId: $enrollmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'learner_attempt',
                resultId: $attemptId,
                completedAt: $now,
            );
            $this->audit->recordTenant(
                'learner.attempt.started',
                'learner_attempt',
                $attemptId,
                $correlationId,
            );
            $this->outbox->recordTenant(
                'learner.attempt.started',
                'learner_attempt',
                $attemptId,
                [
                    'enrollment_id' => $enrollmentId,
                    'learner_attempt_id' => $attemptId,
                    'organization_id' => $context->organizationId,
                    'publication_id' => $mission->publicationId,
                ],
                $correlationId,
            );

            return [
                'id' => $attemptId,
                'status' => 'in_progress',
                'replayed' => false,
            ];
        }, 3);
    }

    /**
     * @param  array<mixed>|null  $localRecording
     */
    public function respond(
        User $learner,
        string $attemptId,
        string $checkId,
        string $selectedOptionId,
        ?array $localRecording,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): bool {
        $this->assertUlid($attemptId);
        $this->commands->assertIdempotencyKey($idempotencyKey);
        $recordingReceipt = $this->recordingReceipt($localRecording);

        return DB::transaction(function () use (
            $learner,
            $attemptId,
            $checkId,
            $selectedOptionId,
            $recordingReceipt,
            $idempotencyKey,
            $correlationId,
        ): bool {
            $context = $this->authority->require($learner);
            $attempt = $this->attempt(
                $context->organizationId,
                (string) $context->membershipId,
                $attemptId,
            );
            $policy = $this->policyForAttempt($attempt);
            $fingerprint = $this->commands->fingerprint([
                'check_id' => $checkId,
                'local_recording' => $recordingReceipt,
                'rule_version' => $policy->version,
                'selected_option_id' => $selectedOptionId,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'learner.attempt.response',
                aggregateType: 'learner_attempt',
                aggregateId: $attemptId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                return true;
            }

            if ($this->objectString($attempt, 'status') !== 'in_progress') {
                throw new LearningOperationRejected;
            }

            $mission = $this->missionForAttempt($learner, $attempt);

            if (
                $checkId !== $mission->checkId
                || ! in_array(
                    $selectedOptionId,
                    array_column($mission->checkOptions, 'id'),
                    true,
                )
            ) {
                throw new LearningOperationRejected;
            }

            if (
                $recordingReceipt !== null
                && DB::table('item_responses')
                    ->where(
                        'organization_id',
                        $context->organizationId,
                    )
                    ->where('learner_attempt_id', $attemptId)
                    ->where(
                        'client_capture_id',
                        $recordingReceipt['client_capture_id'],
                    )
                    ->exists()
            ) {
                throw new LearningOperationRejected;
            }

            $responseNumber = (int) DB::table('item_responses')
                ->where('organization_id', $context->organizationId)
                ->where('learner_attempt_id', $attemptId)
                ->max('response_number') + 1;
            $responseId = (string) Str::ulid();
            $now = Date::now()->toImmutable();
            DB::table('item_responses')->insert([
                'id' => $responseId,
                'organization_id' => $context->organizationId,
                'learner_attempt_id' => $attemptId,
                'learner_membership_id' => $context->membershipId,
                'curriculum_owner_organization_id' => $mission
                    ->ownerOrganizationId,
                'curriculum_version_id' => $mission->curriculumVersionId,
                'curriculum_mission_id' => $mission->missionId,
                'learning_item_id' => $mission->learningItemId,
                'response_number' => $responseNumber,
                'check_id' => $checkId,
                'selected_option_id' => $selectedOptionId,
                'check_correct' => hash_equals(
                    $mission->correctOptionId,
                    $selectedOptionId,
                ),
                'client_capture_id' => $recordingReceipt['client_capture_id']
                    ?? null,
                'capture_mime_type' => $recordingReceipt['mime_type'] ?? null,
                'capture_byte_size' => $recordingReceipt['byte_size'] ?? null,
                'capture_duration_ms' => $recordingReceipt['duration_ms']
                    ?? null,
                'captured_at' => $recordingReceipt === null ? null : $now,
                'created_at' => $now,
            ]);
            $this->commands->record(
                actor: $learner,
                operation: 'learner.attempt.response',
                aggregateType: 'learner_attempt',
                aggregateId: $attemptId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'item_response',
                resultId: $responseId,
                completedAt: $now,
            );
            $this->audit->recordTenant(
                'learner.attempt.response_recorded',
                'item_response',
                $responseId,
                $correlationId,
            );
            $this->outbox->recordTenant(
                'learner.attempt.response_recorded',
                'learner_attempt',
                $attemptId,
                [
                    'item_response_id' => $responseId,
                    'learner_attempt_id' => $attemptId,
                    'organization_id' => $context->organizationId,
                ],
                $correlationId,
            );

            return false;
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function submit(
        User $learner,
        string $attemptId,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): array {
        $this->assertUlid($attemptId);
        $this->commands->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $learner,
            $attemptId,
            $idempotencyKey,
            $correlationId,
        ): array {
            $context = $this->authority->require($learner);
            $attempt = $this->attempt(
                $context->organizationId,
                (string) $context->membershipId,
                $attemptId,
            );
            $policy = $this->policyForAttempt($attempt);
            $fingerprint = $this->commands->fingerprint([
                'rule_version' => $policy->version,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'learner.attempt.submit',
                aggregateType: 'learner_attempt',
                aggregateId: $attemptId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );
            $mission = $this->missionForAttempt($learner, $attempt);

            if ($replay instanceof stdClass) {
                return $this->result(
                    $context->organizationId,
                    $attempt,
                    $mission,
                );
            }

            if ($this->objectString($attempt, 'status') !== 'in_progress') {
                throw new LearningIdempotencyConflict;
            }

            $latest = DB::table('item_responses')
                ->where('organization_id', $context->organizationId)
                ->where('learner_attempt_id', $attemptId)
                ->orderByDesc('response_number')
                ->first([
                    'check_correct',
                ]);

            if (! $latest instanceof stdClass) {
                throw new LearningOperationRejected;
            }

            $knowledgeCorrect = (bool) $latest->check_correct;
            $captureReceiptPresent = DB::table('item_responses')
                ->where('organization_id', $context->organizationId)
                ->where('learner_attempt_id', $attemptId)
                ->whereNotNull('client_capture_id')
                ->exists();
            $now = Date::now()->toImmutable();
            $updated = DB::table('learner_attempts')
                ->where('organization_id', $context->organizationId)
                ->where('id', $attemptId)
                ->where('status', 'in_progress')
                ->whereNull('submitted_at')
                ->update([
                    'status' => 'submitted',
                    'submitted_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new LearningOperationRejected;
            }

            DB::table('attempt_submissions')->insert([
                'organization_id' => $context->organizationId,
                'learner_attempt_id' => $attemptId,
                'learner_membership_id' => $context->membershipId,
                'attempt_number' => $this->objectInteger(
                    $attempt,
                    'attempt_number',
                ),
                'knowledge_score' => $policy->knowledgeScore(
                    $knowledgeCorrect,
                ),
                'knowledge_check_correct' => $knowledgeCorrect,
                'local_capture_receipt_present' => $captureReceiptPresent,
                'knowledge_state' => $policy->knowledgeState(
                    $knowledgeCorrect,
                ),
                'spoken_state' => 'unassessed',
                'result_key' => $policy->resultKey($knowledgeCorrect),
                'rule_version' => $policy->version,
                'submitted_at' => $now,
                'created_at' => $now,
            ]);
            $masteryId = $this->projectMastery(
                attempt: $attempt,
                correct: $knowledgeCorrect,
                submittedAt: $now,
                policy: $policy,
            );
            $review = $this->scheduleReview(
                attempt: $attempt,
                correct: $knowledgeCorrect,
                submittedAt: $now,
                policy: $policy,
            );
            $this->commands->record(
                actor: $learner,
                operation: 'learner.attempt.submit',
                aggregateType: 'learner_attempt',
                aggregateId: $attemptId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'attempt_submission',
                resultId: $attemptId,
                completedAt: $now,
            );
            $this->audit->recordTenant(
                'learner.attempt.submitted',
                'learner_attempt',
                $attemptId,
                $correlationId,
            );
            $this->outbox->recordTenant(
                'learner.attempt.submitted',
                'learner_attempt',
                $attemptId,
                [
                    'learner_attempt_id' => $attemptId,
                    'mastery_state_id' => $masteryId,
                    'organization_id' => $context->organizationId,
                    'review_schedule_id' => $review['id'],
                ],
                $correlationId,
            );
            $attempt->status = 'submitted';
            $attempt->submitted_at = $now;

            return $this->result(
                $context->organizationId,
                $attempt,
                $mission,
            );
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(
        User $learner,
        string $enrollmentId,
    ): array {
        $this->assertUlid($enrollmentId);

        return DB::transaction(function () use (
            $learner,
            $enrollmentId,
        ): array {
            $context = $this->authority->require($learner);
            $mission = $this->missions->first($learner, $enrollmentId);

            if ($mission === null) {
                throw new LearningOperationRejected;
            }

            $mastery = DB::table('mastery_states')
                ->where('organization_id', $context->organizationId)
                ->where('enrollment_id', $enrollmentId)
                ->where(
                    'learner_membership_id',
                    $context->membershipId,
                )
                ->where('learning_objective_id', $mission->objectiveId)
                ->lockForUpdate()
                ->first(['knowledge_state', 'spoken_state']);

            if (! $mastery instanceof stdClass) {
                return [
                    'enrollment_id' => $enrollmentId,
                    'objectives' => [],
                ];
            }

            $review = DB::table('review_schedules')
                ->join('learner_attempts', function ($join): void {
                    $join
                        ->on(
                            'learner_attempts.organization_id',
                            '=',
                            'review_schedules.organization_id',
                        )
                        ->on(
                            'learner_attempts.id',
                            '=',
                            'review_schedules.source_attempt_id',
                        );
                })
                ->where(
                    'review_schedules.organization_id',
                    $context->organizationId,
                )
                ->where('review_schedules.enrollment_id', $enrollmentId)
                ->where(
                    'review_schedules.learning_objective_id',
                    $mission->objectiveId,
                )
                ->orderByDesc('learner_attempts.attempt_number')
                ->lockForUpdate()
                ->first([
                    'review_schedules.due_at',
                    'review_schedules.timezone',
                    'review_schedules.source_attempt_id',
                ]);

            if (! $review instanceof stdClass) {
                throw new LearningOperationRejected;
            }

            $receiptPresent = DB::table('attempt_submissions')
                ->where('organization_id', $context->organizationId)
                ->where(
                    'learner_attempt_id',
                    $this->objectString($review, 'source_attempt_id'),
                )
                ->value('local_capture_receipt_present');

            return [
                'enrollment_id' => $enrollmentId,
                'objectives' => [[
                    'objective_id' => $mission->objectiveId,
                    'label' => $mission->canDoPayload(),
                    'knowledge_state' => $this->objectString(
                        $mastery,
                        'knowledge_state',
                    ) === 'mastered'
                        ? 'mastered'
                        : 'developing',
                    'spoken_state' => 'unassessed',
                    'local_capture_receipt_present' => (bool) $receiptPresent,
                    'evidence' => $this->evidenceSummary(
                        $context->organizationId,
                        $enrollmentId,
                        $mission->missionId,
                    ),
                    'review_due_at' => $this->utcDateTime(
                        $review,
                        'due_at',
                    ),
                    'review_timezone' => $this->objectString(
                        $review,
                        'timezone',
                    ),
                ]],
            ];
        }, 3);
    }

    private function missionForAttempt(
        User $learner,
        stdClass $attempt,
    ): DailyMissionDefinition {
        $mission = $this->missions->resolve(
            $learner,
            $this->objectString($attempt, 'enrollment_id'),
            $this->objectString($attempt, 'curriculum_mission_id'),
        );
        $this->assertAttemptMatchesMission($attempt, $mission);

        return $mission;
    }

    private function assertAttemptMatchesMission(
        stdClass $attempt,
        DailyMissionDefinition $mission,
    ): void {
        if (
            $mission->enrollmentId
                !== $this->objectString($attempt, 'enrollment_id')
            || $mission->missionId
                !== $this->objectString(
                    $attempt,
                    'curriculum_mission_id',
                )
            || $mission->publicationId
                !== $this->objectString(
                    $attempt,
                    'curriculum_publication_id',
                )
            || $mission->curriculumVersionId
                !== $this->objectString(
                    $attempt,
                    'curriculum_version_id',
                )
            || $mission->ownerOrganizationId
                !== $this->objectString(
                    $attempt,
                    'curriculum_owner_organization_id',
                )
            || $mission->objectiveId
                !== $this->objectString(
                    $attempt,
                    'learning_objective_id',
                )
            || ! hash_equals(
                $mission->manifestHash,
                $this->objectString(
                    $attempt,
                    'publication_manifest_hash',
                ),
            )
            || ! hash_equals(
                $mission->definitionHash,
                $this->objectString(
                    $attempt,
                    'mission_definition_hash',
                ),
            )
        ) {
            throw new LearningOperationRejected;
        }
    }

    private function attempt(
        string $organizationId,
        string $membershipId,
        string $attemptId,
        bool $lock = true,
    ): stdClass {
        $query = DB::table('learner_attempts')
            ->where('organization_id', $organizationId)
            ->where('id', $attemptId)
            ->where('learner_membership_id', $membershipId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $attempt = $query->first();

        if (! $attempt instanceof stdClass) {
            throw new LearningOperationRejected;
        }

        return $attempt;
    }

    private function projectMastery(
        stdClass $attempt,
        bool $correct,
        CarbonImmutable $submittedAt,
        DailySpeakingPolicy $policy,
    ): string {
        $organizationId = $this->objectString(
            $attempt,
            'organization_id',
        );
        $enrollmentId = $this->objectString($attempt, 'enrollment_id');
        $objectiveId = $this->objectString(
            $attempt,
            'learning_objective_id',
        );
        $existing = DB::table('mastery_states')
            ->where('organization_id', $organizationId)
            ->where('enrollment_id', $enrollmentId)
            ->where('learning_objective_id', $objectiveId)
            ->lockForUpdate()
            ->first();

        if (! $existing instanceof stdClass) {
            $id = (string) Str::ulid();
            DB::table('mastery_states')->insert([
                'id' => $id,
                'organization_id' => $organizationId,
                'enrollment_id' => $enrollmentId,
                'learner_membership_id' => $this->objectString(
                    $attempt,
                    'learner_membership_id',
                ),
                'curriculum_owner_organization_id' => $this->objectString(
                    $attempt,
                    'curriculum_owner_organization_id',
                ),
                'curriculum_version_id' => $this->objectString(
                    $attempt,
                    'curriculum_version_id',
                ),
                'curriculum_publication_id' => $this->objectString(
                    $attempt,
                    'curriculum_publication_id',
                ),
                'learning_objective_id' => $objectiveId,
                'knowledge_state' => $correct
                    ? 'mastered'
                    : 'not_mastered',
                'spoken_state' => 'unassessed',
                'best_knowledge_score' => $policy->knowledgeScore(
                    $correct,
                ),
                'evidence_attempt_id' => $this->objectString(
                    $attempt,
                    'id',
                ),
                'rule_version' => $policy->version,
                'mastered_at' => $correct ? $submittedAt : null,
                'lock_version' => 0,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);

            return $id;
        }

        $existingId = $this->objectString($existing, 'id');
        $alreadyMastered = $this->objectString(
            $existing,
            'knowledge_state',
        ) === 'mastered';

        if ($alreadyMastered || ! $correct) {
            return $existingId;
        }

        $updated = DB::table('mastery_states')
            ->where('organization_id', $organizationId)
            ->where('id', $existingId)
            ->where(
                'lock_version',
                $this->objectInteger($existing, 'lock_version'),
            )
            ->update([
                'knowledge_state' => 'mastered',
                'best_knowledge_score' => 100,
                'evidence_attempt_id' => $this->objectString(
                    $attempt,
                    'id',
                ),
                'rule_version' => $policy->version,
                'mastered_at' => $submittedAt,
                'lock_version' => $this->objectInteger(
                    $existing,
                    'lock_version',
                ) + 1,
                'updated_at' => $submittedAt,
            ]);

        if ($updated !== 1) {
            throw new LearningOperationRejected;
        }

        return $existingId;
    }

    /**
     * @return array{id: string, due_at: CarbonImmutable, timezone: string}
     */
    private function scheduleReview(
        stdClass $attempt,
        bool $correct,
        CarbonImmutable $submittedAt,
        DailySpeakingPolicy $policy,
    ): array {
        $organizationId = $this->objectString(
            $attempt,
            'organization_id',
        );
        $timezone = DB::table('organizations')
            ->where('id', $organizationId)
            ->lockForUpdate()
            ->value('timezone');

        if (! is_string($timezone) || $timezone === '') {
            throw new LearningOperationRejected;
        }

        $dueAt = $correct
            ? $submittedAt
                ->setTimezone($timezone)
                ->addDay()
                ->setTime($policy->reviewHourLocal, 0)
                ->utc()
            : $submittedAt;
        $id = (string) Str::ulid();
        DB::table('review_schedules')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'enrollment_id' => $this->objectString(
                $attempt,
                'enrollment_id',
            ),
            'learner_membership_id' => $this->objectString(
                $attempt,
                'learner_membership_id',
            ),
            'curriculum_owner_organization_id' => $this->objectString(
                $attempt,
                'curriculum_owner_organization_id',
            ),
            'curriculum_version_id' => $this->objectString(
                $attempt,
                'curriculum_version_id',
            ),
            'curriculum_publication_id' => $this->objectString(
                $attempt,
                'curriculum_publication_id',
            ),
            'learning_objective_id' => $this->objectString(
                $attempt,
                'learning_objective_id',
            ),
            'source_attempt_id' => $this->objectString($attempt, 'id'),
            'schedule_kind' => $policy->scheduleKind($correct),
            'rule_version' => $policy->version,
            'timezone' => $timezone,
            'due_at' => $dueAt,
            'created_at' => $submittedAt,
        ]);

        return [
            'id' => $id,
            'due_at' => $dueAt,
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function result(
        string $organizationId,
        stdClass $attempt,
        DailyMissionDefinition $mission,
    ): array {
        $attemptId = $this->objectString($attempt, 'id');
        $submission = DB::table('attempt_submissions')
            ->where('organization_id', $organizationId)
            ->where('learner_attempt_id', $attemptId)
            ->first([
                'knowledge_check_correct',
                'local_capture_receipt_present',
            ]);
        $review = DB::table('review_schedules')
            ->where('organization_id', $organizationId)
            ->where('source_attempt_id', $attemptId)
            ->where('learning_objective_id', $mission->objectiveId)
            ->first(['due_at', 'timezone']);

        if (
            ! $submission instanceof stdClass
            || ! $review instanceof stdClass
        ) {
            throw new LearningOperationRejected;
        }

        $attemptNumber = $this->objectInteger(
            $attempt,
            'attempt_number',
        );
        $masteredAtCutoff = DB::table('learner_attempts')
            ->join('attempt_submissions', function ($join): void {
                $join
                    ->on(
                        'attempt_submissions.organization_id',
                        '=',
                        'learner_attempts.organization_id',
                    )
                    ->on(
                        'attempt_submissions.learner_attempt_id',
                        '=',
                        'learner_attempts.id',
                    );
            })
            ->where(
                'learner_attempts.organization_id',
                $organizationId,
            )
            ->where(
                'learner_attempts.enrollment_id',
                $this->objectString($attempt, 'enrollment_id'),
            )
            ->where(
                'learner_attempts.learner_membership_id',
                $this->objectString($attempt, 'learner_membership_id'),
            )
            ->where(
                'learner_attempts.learning_objective_id',
                $mission->objectiveId,
            )
            ->where(
                'learner_attempts.attempt_number',
                '<=',
                $attemptNumber,
            )
            ->where(
                'attempt_submissions.knowledge_check_correct',
                true,
            )
            ->exists();

        return [
            'attempt_id' => $attemptId,
            'check_state' => (bool) $submission->knowledge_check_correct
                ? 'correct'
                : 'incorrect',
            'knowledge_state' => $masteredAtCutoff
                ? 'mastered'
                : 'developing',
            'spoken_state' => 'unassessed',
            'local_capture_receipt_present' => (bool) $submission
                ->local_capture_receipt_present,
            'can_do' => $mission->canDoPayload(),
            'evidence' => $this->evidenceSummary(
                $organizationId,
                $this->objectString($attempt, 'enrollment_id'),
                $mission->missionId,
                $attemptNumber,
            ),
            'review' => [
                'due_at' => $this->utcDateTime($review, 'due_at'),
                'timezone' => $this->objectString($review, 'timezone'),
            ],
        ];
    }

    /**
     * @return array{
     *   first_attempt_id: string,
     *   first_attempt_number: int,
     *   latest_attempt_id: string,
     *   latest_attempt_number: int,
     *   best_attempt_id: string,
     *   best_attempt_number: int
     * }
     */
    private function evidenceSummary(
        string $organizationId,
        string $enrollmentId,
        string $missionId,
        ?int $attemptNumberCutoff = null,
    ): array {
        $query = DB::table('learner_attempts')
            ->join('attempt_submissions', function ($join): void {
                $join
                    ->on(
                        'attempt_submissions.organization_id',
                        '=',
                        'learner_attempts.organization_id',
                    )
                    ->on(
                        'attempt_submissions.learner_attempt_id',
                        '=',
                        'learner_attempts.id',
                    );
            })
            ->where('learner_attempts.organization_id', $organizationId)
            ->where('learner_attempts.enrollment_id', $enrollmentId)
            ->where('learner_attempts.curriculum_mission_id', $missionId);

        if ($attemptNumberCutoff !== null) {
            $query->where(
                'learner_attempts.attempt_number',
                '<=',
                $attemptNumberCutoff,
            );
        }

        $attempts = $query
            ->orderBy('learner_attempts.attempt_number')
            ->get([
                'learner_attempts.id',
                'learner_attempts.attempt_number',
                'attempt_submissions.knowledge_score',
            ]);

        if ($attempts->isEmpty()) {
            throw new LearningOperationRejected;
        }

        $first = $attempts->first();
        $latest = $attempts->last();
        $best = $attempts
            ->sortBy([
                ['knowledge_score', 'desc'],
                ['attempt_number', 'asc'],
            ])
            ->first();

        if ($best === null) {
            throw new LearningOperationRejected;
        }

        return [
            'first_attempt_id' => $this->objectString($first, 'id'),
            'first_attempt_number' => $this->objectInteger(
                $first,
                'attempt_number',
            ),
            'latest_attempt_id' => $this->objectString($latest, 'id'),
            'latest_attempt_number' => $this->objectInteger(
                $latest,
                'attempt_number',
            ),
            'best_attempt_id' => $this->objectString($best, 'id'),
            'best_attempt_number' => $this->objectInteger(
                $best,
                'attempt_number',
            ),
        ];
    }

    /**
     * @param  array<mixed>|null  $recording
     * @return array{
     *   captured: true,
     *   client_capture_id: string,
     *   mime_type: string,
     *   byte_size: int,
     *   duration_ms: int
     * }|null
     */
    private function recordingReceipt(?array $recording): ?array
    {
        if ($recording === null) {
            return null;
        }

        $mimeTypes = config('learning.capture.mime_types');
        $minimumBytes = config('learning.capture.minimum_bytes');
        $maximumBytes = config('learning.capture.maximum_bytes');
        $minimumDuration = config(
            'learning.capture.minimum_duration_ms',
        );
        $maximumDuration = config(
            'learning.capture.maximum_duration_ms',
        );

        $captured = $recording['captured'] ?? null;
        $clientCaptureId = $recording['client_capture_id'] ?? null;
        $mimeType = $recording['mime_type'] ?? null;
        $byteSize = $recording['byte_size'] ?? null;
        $durationMs = $recording['duration_ms'] ?? null;

        if (
            array_is_list($recording)
            || array_diff(
                array_keys($recording),
                [
                    'captured',
                    'client_capture_id',
                    'mime_type',
                    'byte_size',
                    'duration_ms',
                ],
            ) !== []
            || $captured !== true
            || ! is_string($clientCaptureId)
            || ! Str::isUuid($clientCaptureId)
            || ! is_string($mimeType)
            || ! is_array($mimeTypes)
            || ! in_array($mimeType, $mimeTypes, true)
            || ! is_int($byteSize)
            || ! is_int($minimumBytes)
            || ! is_int($maximumBytes)
            || $byteSize < $minimumBytes
            || $byteSize > $maximumBytes
            || ! is_int($durationMs)
            || ! is_int($minimumDuration)
            || ! is_int($maximumDuration)
            || $durationMs < $minimumDuration
            || $durationMs > $maximumDuration
        ) {
            throw new LearningOperationRejected;
        }

        return [
            'captured' => true,
            'client_capture_id' => $clientCaptureId,
            'mime_type' => $mimeType,
            'byte_size' => $byteSize,
            'duration_ms' => $durationMs,
        ];
    }

    private function assertUlid(string $id): void
    {
        if (! Str::isUlid($id)) {
            throw new LearningOperationRejected;
        }
    }

    private function objectString(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return $value;
    }

    private function objectInteger(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LearningOperationRejected;
    }

    private function utcDateTime(stdClass $row, string $field): string
    {
        try {
            return CarbonImmutable::parse(
                $this->objectString($row, $field),
            )->utc()->toIso8601String();
        } catch (\Throwable) {
            throw new LearningOperationRejected;
        }
    }

    private function policyForAttempt(
        stdClass $attempt,
    ): DailySpeakingPolicy {
        return $this->policies->forVersion(
            $this->objectString($attempt, 'rule_version'),
        );
    }
}
