<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationSessionService;
use App\Conversation\ConversationTurnService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConversationOrchestrationTest extends TenancyIntegrationTestCase
{
    public function test_tutor_mode_returns_separate_correction_without_learning_writes(): void
    {
        [$learner, , $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $turns = $this->app->make(ConversationTurnService::class);
        $learningBefore = $this->learningCounts();

        $session = $this->contexts()->run(
            $context,
            fn (): array => $sessions->start(
                learner: $learner,
                personaKey: 'patient-friend-prototype',
                scenarioKey: 'everyday-friend-prototype',
                coachingMode: 'tutor',
                uiLocale: 'kn-IN',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $turn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: 'I enjoy learning English after work.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('tutor', $session['coaching_mode']);
        $this->assertSame('completed', $turn['status']);
        $this->assertSame(
            'Good try. Keep the conversation going with one clear next idea.',
            $turn['assistant_reply'],
        );
        $this->assertIsArray($turn['priority_correction']);
        $this->assertTrue($turn['priority_correction']['retry_recommended']);
        $this->assertNotSame(
            $turn['assistant_reply'],
            $turn['priority_correction']['correction_en'],
        );
        $this->assertSame(
            1,
            DB::table('prompt_versions')->where('coaching_mode', 'tutor')->count(),
        );
        $this->assertStringContainsString(
            'You are Swathi',
            (string) DB::table('prompt_versions')
                ->where('prompt_key', 'friend.turn.v1')
                ->where('coaching_mode', 'tutor')
                ->where('status', 'active')
                ->value('body'),
        );
        $this->assertSame(1, DB::table('correction_suggestions')->count());
        $this->assertSame(1, DB::table('learner_error_patterns')->count());
        $this->assertSame($learningBefore, $this->learningCounts());
    }

    public function test_unsafe_model_output_uses_curated_fallback(): void
    {
        [$learner, , $context] = $this->learner();
        $sessions = $this->app->make(ConversationSessionService::class);
        $turns = $this->app->make(ConversationTurnService::class);
        $learningBefore = $this->learningCounts();

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
        $turn = $this->contexts()->run(
            $context,
            fn (): array => $turns->create(
                learner: $learner,
                sessionId: (string) $session['id'],
                prototypeUtterance: 'Please trigger unsafe-fixture output now.',
                idempotencyKey: (string) Str::uuid(),
            ),
        );

        $this->assertSame('completed', $turn['status']);
        $this->assertSame('fallback', $turn['safety_state']);
        $this->assertSame('safe_fallback', $turn['understood_intent']);
        $this->assertSame(
            'Thank you. Let us continue with a simple next sentence.',
            $turn['assistant_reply'],
        );
        $this->assertSame(
            'safety.fallback',
            $turn['priority_correction']['error_category'],
        );
        $this->assertStringNotContainsString('<', (string) $turn['assistant_reply']);
        $this->assertSame($learningBefore, $this->learningCounts());
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
            'conversation-'.strtolower((string) Str::ulid()),
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
