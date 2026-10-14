<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\ConversationCommandIdempotency;
use App\Conversation\Dialogue\ConversationRecoveryStore;
use App\Conversation\Dialogue\DialogueTurnOutcomeUnknown;
use App\Conversation\Dialogue\DialogueTurnReservationService;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

final class DialogueTurnReservationTest extends TenancyIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set(['voice.allowed_organization_ids' => [], 'voice.upload_enabled' => true]);
        Storage::fake(ConversationRecoveryStore::DISK);
    }

    public function test_reservation_survives_provider_rollback_and_same_or_new_key_cannot_redispatch(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        $key = (string) Str::uuid();
        $fingerprint = hash('sha256', 'synthetic private utterance');
        $calls = 0;
        $admit = function (stdClass $state, stdClass $scopedSession) use (&$calls, $context, $member, $session): int {
            $calls++;
            self::assertSame($context->organizationId, $state->organization_id);
            self::assertSame((string) $member->id, $state->learner_membership_id);
            self::assertSame($session, $scopedSession->id);

            return (int) $state->revision;
        };
        $id = $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, $key, $fingerprint, $admit));
        self::assertIsString($id);
        DB::beginTransaction();
        $this->turn($context, $member, $session);
        DB::rollBack();
        self::assertSame(0, DB::table('conversation_turns')->count());
        foreach ([$key, (string) Str::uuid()] as $retryKey) {
            try {
                $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, $retryKey, $fingerprint, $admit));
                self::fail('An unknown logical-turn outcome must not be redispatched.');
            } catch (DialogueTurnOutcomeUnknown $exception) {
                self::assertSame('conversation_turn_outcome_unknown', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
        self::assertSame(1, $calls);
        $row = DB::table('conversation_dialogue_turn_reservations')->sole();
        self::assertSame('reserved', $row->status);
        self::assertNull($row->completed_turn_id);
        self::assertNull($row->completed_at);
        self::assertStringNotContainsString('synthetic private utterance', json_encode($row, JSON_THROW_ON_ERROR));
        self::assertSame(0, DB::table('ai_cost_ledger')->count());
    }

    public function test_matching_committed_failed_turn_replays_without_re_admission(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        $key = (string) Str::uuid();
        $fingerprint = hash('sha256', 'synthetic request');
        $id = $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, $key, $fingerprint, fn (stdClass $state): int => (int) $state->revision));
        self::assertIsString($id);
        $turnId = $this->contexts()->run($context, fn (): string => DB::transaction(function () use ($user, $member, $context, $session, $key, $fingerprint, $id): string {
            $turn = $this->turn($context, $member, $session);
            $this->app->make(ConversationCommandIdempotency::class)->record($user, 'conversation.turn.create', 'conversation_session', $session, $key, $fingerprint, 'conversation_turn', $turn, Date::now());
            $this->service()->complete($context->organizationId, (string) $member->id, $session, $id, $turn);

            return $turn;
        }));
        self::assertSame('completed', DB::table('conversation_dialogue_turn_reservations')->sole()->status);
        self::assertSame($turnId, DB::table('conversation_dialogue_turn_reservations')->sole()->completed_turn_id);
        $admit = static function (): int {
            self::fail('Exact committed replay must not call admission or providers.');
        };
        self::assertNull($this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, $key, $fingerprint, $admit)));
        $this->expectException(ConversationIdempotencyConflict::class);
        $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, $key, hash('sha256', 'changed'), $admit));
    }

    public function test_denied_quota_or_invalid_admission_leaves_no_reservation(): void
    {
        [$user, , $context, $session] = $this->fixture();
        try {
            $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, (string) Str::uuid(), hash('sha256', 'request'), static function (): int {
                throw new EntitlementDenied(EntitlementDenied::AI_EXHAUSTED);
            }));
            self::fail('Exhausted quota must reject before admission.');
        } catch (EntitlementDenied) {
            self::assertSame(0, DB::table('conversation_dialogue_turn_reservations')->count());
        }
        try {
            $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, (string) Str::uuid(), hash('sha256', 'request'), static fn (): int => 99));
            self::fail('Incorrect expected revision must be rejected.');
        } catch (ConversationIdempotencyConflict) {
            self::assertSame(0, DB::table('conversation_dialogue_turn_reservations')->count());
        }
    }

    public function test_processing_consent_is_required_before_any_admission_marker(): void
    {
        [$user, , $context, $session] = $this->fixture(false);
        try {
            $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, (string) Str::uuid(), hash('sha256', 'request'), static fn (): int => 0));
            self::fail('Recovery acknowledgement alone cannot authorize dispatch.');
        } catch (VoiceOperationRejected) {
            self::assertSame(0, DB::table('conversation_dialogue_turn_reservations')->count());
        }
    }

    public function test_other_membership_closed_and_expired_sessions_do_not_reserve(): void
    {
        [$user, , $context, $session] = $this->fixture();
        [$other, , $otherContext] = $this->fixture();
        try {
            $this->contexts()->run($otherContext, fn (): ?string => $this->service()->reserve($other, $session, (string) Str::uuid(), hash('sha256', 'request'), static fn (): int => 0));
            self::fail('Same-organization other learner cannot reserve.');
        } catch (ConversationOperationRejected) {
            self::assertSame(0, DB::table('conversation_dialogue_turn_reservations')->count());
        }
        foreach ([['closed_at' => now()], ['closed_at' => null, 'expires_at' => now()->subSecond()]] as $update) {
            DB::table('conversation_dialogue_states')->where('conversation_session_id', $session)->update($update);
            try {
                $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, (string) Str::uuid(), hash('sha256', 'request'), static fn (): int => 0));
                self::fail('Closed/expired session cannot reserve.');
            } catch (ConversationOperationRejected) {
                self::assertSame(0, DB::table('conversation_dialogue_turn_reservations')->count());
            }
        }
    }

    public function test_changed_payload_cannot_reuse_unknown_reservation_and_completion_requires_command(): void
    {
        [$user, $member, $context, $session] = $this->fixture();
        $key = (string) Str::uuid();
        $id = $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, $key, hash('sha256', 'first'), static fn (): int => 0));
        self::assertIsString($id);
        try {
            $this->contexts()->run($context, fn (): ?string => $this->service()->reserve($user, $session, $key, hash('sha256', 'changed'), static fn (): int => 0));
            self::fail('Changed payload must conflict.');
        } catch (ConversationIdempotencyConflict) {
            self::assertSame(1, DB::table('conversation_dialogue_turn_reservations')->count());
        }
        $this->expectException(ConversationOperationRejected::class);
        $this->service()->complete($context->organizationId, (string) $member->id, $session, $id, (string) Str::ulid());
    }

    private function service(): DialogueTurnReservationService
    {
        return $this->app->make(DialogueTurnReservationService::class);
    }

    private function turn(OrganizationContext $context, Membership $member, string $session): string
    {
        $turn = (string) Str::ulid();
        DB::table('conversation_turns')->insert([
            'id' => $turn, 'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id,
            'conversation_session_id' => $session, 'turn_number' => 1, 'status' => 'failed', 'utterance_digest' => hash('sha256', 'synthetic'),
            'failed_stage' => 'model', 'failure_code' => 'synthetic_provider_failure', 'accepted_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $turn;
    }

    /** @return array{User, Membership, OrganizationContext, string} */
    private function fixture(bool $grantConsent = true): array
    {
        $organization = Organization::query()->where('system_key', Organization::DIRECT_SYSTEM_KEY)->firstOrFail();
        $user = $this->createUser();
        [$member, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole($context, 'turn-reservation-'.strtolower((string) Str::ulid()));
        $permission = Permission::query()->where('key', 'learning.participate')->firstOrFail();
        DB::table('membership_roles')->insert(['organization_id' => $context->organizationId, 'membership_id' => $member->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['organization_id' => $context->organizationId, 'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('learner_profiles')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id,
            'adult_confirmed_at' => now(), 'onboarding_completed_at' => now(), 'created_at' => now(),
        ]);
        if ($grantConsent) {
            $this->contexts()->run($context, fn (): array => $this->app->make(VoiceConsentService::class)->grant($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        }
        $persona = DB::table('persona_versions')->where('organization_id', $context->organizationId)->first();
        $scenario = DB::table('scenario_versions')->where('organization_id', $context->organizationId)->first();
        self::assertInstanceOf(stdClass::class, $persona);
        self::assertInstanceOf(stdClass::class, $scenario);
        $session = (string) Str::ulid();
        DB::table('conversation_sessions')->insert([
            'id' => $session, 'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id,
            'persona_id' => $persona->persona_id, 'persona_version_id' => $persona->id, 'persona_definition_hash' => $persona->definition_hash,
            'scenario_id' => $scenario->scenario_id, 'scenario_version_id' => $scenario->id, 'scenario_definition_hash' => $scenario->definition_hash,
            'mode' => 'roleplay', 'coaching_mode' => 'gentle', 'status' => 'active', 'ui_locale' => 'en-IN', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $artifact = $this->app->make(ConversationRecoveryStore::class)->persist($context->organizationId, (string) $member->id, $session, null, ['opening_reply' => ['text' => 'Synthetic opener.', 'locale' => 'en-IN']]);
        DB::table('conversation_dialogue_states')->insert([
            'organization_id' => $context->organizationId, 'learner_membership_id' => $member->id, 'conversation_session_id' => $session,
            'flow' => 'roleplay_dialogue', 'state' => 'awaiting_reply', 'start_artifact_id' => $artifact,
            'notice_version' => 'synthetic-notice', 'notice_accepted_at' => now(), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $member, $context, $session];
    }
}
