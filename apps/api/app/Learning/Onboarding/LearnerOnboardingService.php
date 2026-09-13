<?php

declare(strict_types=1);

namespace App\Learning\Onboarding;

use App\Audit\AuditEventRecorder;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\LearningCommandIdempotency;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class LearnerOnboardingService
{
    public function __construct(
        private LearnerAuthority $authority,
        private LearningCommandIdempotency $commands,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function complete(
        User $learner,
        bool $adultConfirmed,
        string $goalKey,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): LearnerOnboardingResult {
        $this->commands->assertIdempotencyKey($idempotencyKey);

        if (
            ! $adultConfirmed
            || ! in_array(
                $goalKey,
                $this->goalKeys(),
                true,
            )
        ) {
            throw new LearningOperationRejected;
        }

        return DB::transaction(function () use (
            $learner,
            $goalKey,
            $idempotencyKey,
            $correlationId,
        ): LearnerOnboardingResult {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $fingerprint = $this->commands->fingerprint([
                'adult_confirmed' => true,
                'goal_key' => $goalKey,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'learner.onboarding.complete',
                aggregateType: 'membership',
                aggregateId: $membershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                return $this->existing(
                    $context->organizationId,
                    $membershipId,
                    true,
                );
            }

            if (
                DB::table('learner_profiles')
                    ->where('organization_id', $context->organizationId)
                    ->where('learner_membership_id', $membershipId)
                    ->lockForUpdate()
                    ->exists()
            ) {
                throw new LearningIdempotencyConflict;
            }

            $now = Date::now()->toImmutable();
            $profileId = (string) Str::ulid();
            $goalId = (string) Str::ulid();
            DB::table('learner_profiles')->insert([
                'id' => $profileId,
                'organization_id' => $context->organizationId,
                'learner_membership_id' => $membershipId,
                'adult_confirmed_at' => $now,
                'onboarding_completed_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('learner_goals')->insert([
                'id' => $goalId,
                'organization_id' => $context->organizationId,
                'learner_profile_id' => $profileId,
                'learner_membership_id' => $membershipId,
                'goal_key' => $goalKey,
                'selected_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('learner_profile_current_goals')->insert([
                'id' => $goalId,
                'organization_id' => $context->organizationId,
                'learner_profile_id' => $profileId,
                'learner_membership_id' => $membershipId,
                'goal_key' => $goalKey,
                'version' => 1,
                'corrected_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->commands->record(
                actor: $learner,
                operation: 'learner.onboarding.complete',
                aggregateType: 'membership',
                aggregateId: $membershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'learner_profile',
                resultId: $profileId,
                completedAt: $now,
            );
            $this->audit->recordTenant(
                'learner.onboarding.completed',
                'learner_profile',
                $profileId,
                $correlationId,
            );
            $this->outbox->recordTenant(
                'learner.onboarding.completed',
                'learner_profile',
                $profileId,
                [
                    'learner_goal_id' => $goalId,
                    'learner_profile_id' => $profileId,
                    'membership_id' => $membershipId,
                    'organization_id' => $context->organizationId,
                ],
                $correlationId,
            );

            return new LearnerOnboardingResult(
                profileId: $profileId,
                goalId: $goalId,
                goalKey: $goalKey,
                replayed: false,
            );
        }, 3);
    }

    private function existing(
        string $organizationId,
        string $membershipId,
        bool $replayed,
    ): LearnerOnboardingResult {
        $row = DB::table('learner_profiles')
            ->join('learner_goals', function ($join): void {
                $join
                    ->on(
                        'learner_goals.organization_id',
                        '=',
                        'learner_profiles.organization_id',
                    )
                    ->on(
                        'learner_goals.learner_profile_id',
                        '=',
                        'learner_profiles.id',
                    )
                    ->on(
                        'learner_goals.learner_membership_id',
                        '=',
                        'learner_profiles.learner_membership_id',
                    );
            })
            ->join('learner_profile_current_goals', function ($join): void {
                $join
                    ->on(
                        'learner_profile_current_goals.organization_id',
                        '=',
                        'learner_profiles.organization_id',
                    )
                    ->on(
                        'learner_profile_current_goals.learner_profile_id',
                        '=',
                        'learner_profiles.id',
                    )
                    ->on(
                        'learner_profile_current_goals.learner_membership_id',
                        '=',
                        'learner_profiles.learner_membership_id',
                    );
            })
            ->where(
                'learner_profiles.organization_id',
                $organizationId,
            )
            ->where(
                'learner_profiles.learner_membership_id',
                $membershipId,
            )
            ->first([
                'learner_profiles.id as profile_id',
                'learner_goals.id as goal_id',
                'learner_profile_current_goals.goal_key',
            ]);

        if (! $row instanceof stdClass) {
            throw new LearningOperationRejected;
        }

        return new LearnerOnboardingResult(
            profileId: $this->string($row, 'profile_id'),
            goalId: $this->string($row, 'goal_id'),
            goalKey: $this->string($row, 'goal_key'),
            replayed: $replayed,
        );
    }

    /**
     * @return list<string>
     */
    private function goalKeys(): array
    {
        $keys = config('learning.goal_keys');

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_filter(
            $keys,
            static fn (mixed $key): bool => is_string($key),
        ));
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return $value;
    }
}
