<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationSessionService;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\RoleplayStageService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RoleplaySessionTest extends TenancyIntegrationTestCase
{
    public function test_roleplay_stages_emit_advisory_feedback_without_mastery_writes(): void
    {
        [$learner, $membership, $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $stages = $this->app->make(RoleplayStageService::class);
        $learningBefore = $this->learningCounts();

        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'clinic-reception-roleplay-prototype',
                scenarioKey: 'clinic-reception-roleplay-scenario',
                coachingMode: 'gentle',
                uiLocale: 'en-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertFalse($session['replayed']);
        $this->assertSame('roleplay', $session['mode']);
        $this->assertSame('observe', $session['stage']);
        $this->assertCount(2, $session['objectives']);
        $this->assertSame(
            'greet-receptionist',
            $session['objectives'][0]['key'],
        );
        $this->assertSame([], $session['feedback']);
        $this->assertSame(
            1,
            DB::table('conversation_session_stage_events')
                ->where('conversation_session_id', $session['id'])
                ->count(),
        );

        $rehearse = $this->contexts()->run(
            $context,
            fn (): array => $stages->advance(
                learner: $learner,
                sessionId: (string) $session['id'],
                toStage: 'rehearse',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $this->assertSame('rehearse', $rehearse['stage']);

        $perform = $this->contexts()->run(
            $context,
            fn (): array => $stages->advance(
                learner: $learner,
                sessionId: (string) $session['id'],
                toStage: 'perform',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $this->assertSame('perform', $perform['stage']);

        $reflect = $this->contexts()->run(
            $context,
            fn (): array => $stages->advance(
                learner: $learner,
                sessionId: (string) $session['id'],
                toStage: 'reflect',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('reflect', $reflect['stage']);
        $this->assertCount(2, $reflect['feedback']);
        $this->assertSame('met', $reflect['feedback'][0]['completion_state']);
        $this->assertSame('unmet', $reflect['feedback'][1]['completion_state']);
        $this->assertSame(
            hash('sha256', 'greet-receptionist|'.$session['id']),
            $reflect['feedback'][0]['evidence_digest'],
        );
        $this->assertSame($learningBefore, $this->learningCounts());
        $this->assertSame(
            2,
            DB::table('roleplay_feedback_evidence')
                ->where('conversation_session_id', $session['id'])
                ->count(),
        );
    }

    public function test_friend_session_rejects_stage_advance(): void
    {
        [$learner, , $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $stages = $this->app->make(RoleplayStageService::class);

        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'gentle',
                uiLocale: 'kn-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $this->assertSame('friend', $session['mode']);
        $this->assertNull($session['stage']);

        try {
            $this->contexts()->run(
                $context,
                fn (): array => $stages->advance(
                    learner: $learner,
                    sessionId: (string) $session['id'],
                    toStage: 'rehearse',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );
            self::fail('Expected friend stage advance to fail closed.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_cross_tenant_stage_advance_is_denied(): void
    {
        [$owner, , $ownerContext] = $this->learner();
        [$intruder, , $intruderContext] = $this->learner(
            $this->createOrganization('Roleplay tenant B'),
        );
        $sessions = $this->app->make(ConversationSessionService::class);
        $stages = $this->app->make(RoleplayStageService::class);

        $session = $this->contexts()->run(
            $ownerContext,
            fn (): array => $sessions->start(
                learner: $owner,
                personaKey: 'clinic-reception-roleplay-prototype',
                scenarioKey: 'clinic-reception-roleplay-scenario',
                coachingMode: 'gentle',
                uiLocale: 'en-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        try {
            $this->contexts()->run(
                $intruderContext,
                fn (): array => $stages->advance(
                    learner: $intruder,
                    sessionId: (string) $session['id'],
                    toStage: 'rehearse',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );
            self::fail('Expected cross-tenant stage advance to fail.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(
        ?Organization $organization = null,
    ): array {
        $organization ??= Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
        );
        $role = $this->createRole(
            $context,
            'roleplay-'.strtolower((string) Str::ulid()),
        );
        $permission = Permission::query()
            ->where('key', 'learning.participate')
            ->firstOrFail();
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$user, $membership, $context];
    }

    /**
     * @return array<string, int>
     */
    private function learningCounts(): array
    {
        return collect([
            'learner_attempts',
            'item_responses',
            'attempt_submissions',
            'mastery_states',
            'review_schedules',
            'learning_commands',
        ])->mapWithKeys(
            static fn (string $table): array => [
                $table => DB::table($table)->count(),
            ],
        )->all();
    }
}
