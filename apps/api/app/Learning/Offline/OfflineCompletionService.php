<?php

declare(strict_types=1);

namespace App\Learning\Offline;

use App\Learning\Attempts\DailyMissionAttemptService;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\LearningCommandIdempotency;
use App\Learning\Missions\DailyMissionReader;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use stdClass;

final readonly class OfflineCompletionService
{
    public function __construct(
        private LearnerAuthority $authority,
        private DailyMissionReader $missions,
        private DailyMissionAttemptService $attempts,
        private LearningCommandIdempotency $commands,
        private OfflineLeaseSigner $leases,
    ) {}

    /**
     * @param  array<mixed>|null  $localRecording
     * @return array<string, mixed>
     */
    public function complete(
        User $learner,
        string $bundleId,
        string $lease,
        string $clientAttemptId,
        string $checkId,
        string $selectedOptionId,
        ?array $localRecording,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): array {
        if (
            ! Str::isUlid($bundleId)
            || ! Str::isUuid($clientAttemptId)
        ) {
            throw new LearningOperationRejected;
        }

        $this->commands->assertIdempotencyKey($idempotencyKey);
        $claims = $this->leases->verify($lease);
        $context = $this->authority->require($learner);
        $this->assertClaim($claims, 'purpose', 'offline-mission-completion');
        $this->assertClaim(
            $claims,
            'schema',
            (string) config('offline.bundle_schema'),
        );
        $this->assertClaim($claims, 'bundle_id', $bundleId);
        $this->assertClaim(
            $claims,
            'user_id',
            (string) $learner->getKey(),
        );
        $this->assertClaim(
            $claims,
            'organization_id',
            $context->organizationId,
        );
        $this->assertClaim(
            $claims,
            'membership_id',
            (string) $context->membershipId,
        );
        $expiresAt = $this->claimDate($claims, 'expires_at');
        $issuedAt = $this->claimDate($claims, 'issued_at');

        if (
            $expiresAt->lessThanOrEqualTo(Date::now())
            || $expiresAt->lessThanOrEqualTo($issuedAt)
            || $expiresAt->diffInMinutes($issuedAt, true) > 1440
        ) {
            throw new LearningOperationRejected;
        }

        $enrollmentId = $this->claimString($claims, 'enrollment_id');
        $missionId = $this->claimString($claims, 'mission_id');
        $mission = $this->missions->resolve(
            $learner,
            $enrollmentId,
            $missionId,
        );
        $this->assertClaim(
            $claims,
            'owner_organization_id',
            $mission->ownerOrganizationId,
        );
        $this->assertClaim(
            $claims,
            'curriculum_version_id',
            $mission->curriculumVersionId,
        );
        $this->assertClaim(
            $claims,
            'publication_id',
            $mission->publicationId,
        );
        $this->assertClaim(
            $claims,
            'publication_manifest_hash',
            $mission->manifestHash,
        );
        $this->assertClaim(
            $claims,
            'mission_definition_hash',
            $mission->definitionHash,
        );
        $this->assertClaim(
            $claims,
            'policy_version',
            (string) config('learning.default_rule_version'),
        );

        return DB::transaction(function () use (
            $learner,
            $bundleId,
            $claims,
            $clientAttemptId,
            $checkId,
            $selectedOptionId,
            $localRecording,
            $idempotencyKey,
            $correlationId,
            $context,
            $enrollmentId,
            $missionId,
        ): array {
            $lockedEnrollment = DB::table('enrollments')
                ->where('id', $enrollmentId)
                ->where('organization_id', $context->organizationId)
                ->where(
                    'learner_membership_id',
                    $context->membershipId,
                )
                ->where('status', 'active')
                ->lockForUpdate()
                ->first(['id']);

            if (! $lockedEnrollment instanceof stdClass) {
                throw new LearningOperationRejected;
            }

            $fingerprint = $this->commands->fingerprint([
                'schema' => (string) config('offline.completion_schema'),
                'bundle_id' => $bundleId,
                'lease_id' => $this->claimString($claims, 'lease_id'),
                'client_attempt_id' => $clientAttemptId,
                'check_id' => $checkId,
                'selected_option_id' => $selectedOptionId,
                'local_recording' => $localRecording,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'learner.offline.complete',
                aggregateType: 'enrollment',
                aggregateId: $enrollmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );
            $submitKey = $this->derivedKey(
                $idempotencyKey,
                'submit',
            );

            if ($replay instanceof stdClass) {
                $attemptId = $this->objectString(
                    $replay,
                    'result_id',
                );

                return $this->attempts->submit(
                    $learner,
                    $attemptId,
                    $submitKey,
                    $correlationId,
                );
            }

            $openAttemptExists = DB::table('learner_attempts')
                ->where('organization_id', $context->organizationId)
                ->where('enrollment_id', $enrollmentId)
                ->where('learner_membership_id', $context->membershipId)
                ->where('curriculum_mission_id', $missionId)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->exists();

            if ($openAttemptExists) {
                throw new LearningOperationRejected;
            }

            $started = $this->attempts->start(
                $learner,
                $enrollmentId,
                $missionId,
                $this->derivedKey($idempotencyKey, 'start'),
                $correlationId,
            );
            $attemptId = $started['id'];
            $this->attempts->respond(
                $learner,
                $attemptId,
                $checkId,
                $selectedOptionId,
                $localRecording,
                $this->derivedKey($idempotencyKey, 'response'),
                $correlationId,
            );
            $result = $this->attempts->submit(
                $learner,
                $attemptId,
                $submitKey,
                $correlationId,
            );
            $this->commands->record(
                actor: $learner,
                operation: 'learner.offline.complete',
                aggregateType: 'enrollment',
                aggregateId: $enrollmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'learner_attempt',
                resultId: $attemptId,
                completedAt: Date::now()->toImmutable(),
            );

            return $result;
        }, 3);
    }

    private function derivedKey(string $commandId, string $stage): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'https://global-english.test/offline-completion/'
                .$commandId
                .'/'
                .$stage,
        )->toString();
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertClaim(
        array $claims,
        string $field,
        string $expected,
    ): void {
        if (! hash_equals($expected, $this->claimString($claims, $field))) {
            throw new LearningOperationRejected;
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function claimString(array $claims, string $field): string
    {
        $value = $claims[$field] ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function claimDate(
        array $claims,
        string $field,
    ): CarbonImmutable {
        try {
            return CarbonImmutable::parse(
                $this->claimString($claims, $field),
            );
        } catch (\Throwable) {
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
}
