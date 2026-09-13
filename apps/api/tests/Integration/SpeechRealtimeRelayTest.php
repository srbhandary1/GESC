<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationSessionService;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Realtime\RealtimeSpeechTicketService;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\LearnerSubscriptionService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SpeechRealtimeRelayTest extends TenancyIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('voice.upload_enabled', true);
        config()->set('voice.allowed_organization_ids', []);
    }

    public function test_ticket_requires_consent_and_fail_closes_to_recorded_recovery(): void
    {
        [$learner, , $context] = $this->learner();
        $session = $this->startConversationSession($learner, $context);
        $tickets = $this->app->make(RealtimeSpeechTicketService::class);
        $command = (string) Str::uuid();

        try {
            $this->contexts()->run(
                $context,
                fn (): array => $tickets->issue(
                    $learner,
                    (string) $session['id'],
                    $command,
                ),
            );
            self::fail('Expected missing voice consent to reject the ticket.');
        } catch (VoiceOperationRejected) {
            $this->addToAssertionCount(1);
        }

        $this->grantRequiredConsents($learner, $context);
        $ticket = $this->contexts()->run(
            $context,
            fn (): array => $tickets->issue(
                $learner,
                (string) $session['id'],
                $command,
            ),
        );
        $replay = $this->contexts()->run(
            $context,
            fn (): array => $tickets->issue(
                $learner,
                (string) $session['id'],
                $command,
            ),
        );

        $this->assertSame($ticket['ticket_id'], $replay['ticket_id']);
        $this->assertSame('recorded_recovery', $ticket['transport']);
        $this->assertSame('denied', $ticket['provider_dispatch']);
        $this->assertSame('recorded_upload', $ticket['recovery_route']);
        $this->assertSame('push_to_talk', $ticket['capture_mode']);
        $this->assertFalse($ticket['always_on']);
        $this->assertFalse($ticket['full_duplex']);
        $this->assertFalse($ticket['preconnect_allowed']);
        $this->assertContains(
            'http_enablement_not_authorized',
            $ticket['denial_reasons'],
        );

        $inspection = $this->contexts()->run(
            $context,
            fn (): array => $tickets->inspectSocket(
                $learner,
                (string) $session['id'],
                (string) $ticket['ticket_id'],
            ),
        );
        $this->assertSame('recorded_recovery', $inspection['status']);

        $otherOrganization = $this->createOrganization('Realtime foreign tenant');
        [$otherLearner, , $otherContext] = $this->learner(
            organization: $otherOrganization,
        );
        $this->grantRequiredConsents($otherLearner, $otherContext);
        try {
            $this->contexts()->run(
                $otherContext,
                fn (): array => $tickets->issue(
                    $otherLearner,
                    (string) $session['id'],
                    (string) Str::uuid(),
                ),
            );
            self::fail('Expected cross-tenant realtime ticket to fail.');
        } catch (ConversationOperationRejected) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function learner(?Organization $organization = null): array
    {
        $organization ??= Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole(
            $context,
            'realtime-'.strtolower((string) Str::ulid()),
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
        DB::table('learner_profiles')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $membership->getKey(),
            'adult_confirmed_at' => $now,
            'onboarding_completed_at' => $now,
            'created_at' => $now,
        ]);

        if ($organization->system_key === Organization::DIRECT_SYSTEM_KEY) {
            $this->contexts()->run($context, function () use ($user, $organization): void {
                $this->app->make(EntitlementAccess::class)->snapshot($user);
                $planId = DB::table('entitlement_plans')
                    ->where('organization_id', $organization->getKey())
                    ->where('code', DirectPlanDefaults::MONTHLY_MODULES)
                    ->value('id');
                $this->assertIsString($planId);
                $this->app->make(LearnerSubscriptionService::class)->checkout(
                    $user,
                    $planId,
                    (string) Str::ulid(),
                );
            });
        }

        return [$user, $membership, $context];
    }

    /**
     * @return array<string, mixed>
     */
    private function startConversationSession(
        User $learner,
        OrganizationContext $context,
    ): array {
        return $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(ConversationSessionService::class)
                ->start(
                    learner: $learner,
                    personaKey: 'patient-friend-prototype',
                    scenarioKey: 'everyday-friend-prototype',
                    coachingMode: 'gentle',
                    uiLocale: 'kn-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );
    }

    private function grantRequiredConsents(
        User $learner,
        OrganizationContext $context,
    ): void {
        $consents = $this->app->make(VoiceConsentService::class);
        foreach (
            [VoiceConsentService::UPLOAD, VoiceConsentService::PROVIDER] as $purpose
        ) {
            $this->contexts()->run(
                $context,
                fn (): array => $consents->grant(
                    learner: $learner,
                    purpose: $purpose,
                    requestedLocale: 'en-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );
        }
    }
}
