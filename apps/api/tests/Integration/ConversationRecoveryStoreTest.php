<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\Dialogue\ConversationRecoveryStore;
use App\Conversation\Dialogue\ConversationRecoveryUnavailable;
use App\Conversation\Voice\VoiceConsentService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Operator\LearnerPrivacyInventory;
use App\Operator\PrivacyErasureService;
use App\Tenancy\OrganizationContext;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

final class ConversationRecoveryStoreTest extends TenancyIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('voice.allowed_organization_ids', []);
        Storage::fake(ConversationRecoveryStore::DISK);
    }

    public function test_snapshot_is_encrypted_private_exact_and_database_is_content_free(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        $store = $this->store();
        $payload = ['reply' => 'Synthetic private reply.', 'learner' => 'ನಾನು ಅಭ್ಯಾಸ ಮಾಡುತ್ತೇನೆ.', 'audio' => base64_encode('synthetic reply audio')];
        $id = $store->persist($context->organizationId, (string) $membership->id, $session, null, $payload);
        $row = $this->row($id);
        $ciphertext = Storage::disk(ConversationRecoveryStore::DISK)->get((string) $row->storage_path);
        self::assertIsString($ciphertext);
        self::assertStringNotContainsString($payload['reply'], $ciphertext);
        self::assertStringContainsString($payload['reply'], Crypt::decryptString($ciphertext));
        self::assertSame(hash('sha256', $ciphertext), $row->ciphertext_sha256);
        self::assertSame('private', Storage::disk(ConversationRecoveryStore::DISK)->getVisibility((string) $row->storage_path));
        self::assertFalse(config('filesystems.disks.conversation_recovery.serve'));
        self::assertSame($payload, $store->read($context->organizationId, (string) $membership->id, $session, $id));
        $metadata = json_encode(DB::table('conversation_recovery_artifacts')->get(), JSON_THROW_ON_ERROR);
        foreach ($payload as $value) {
            self::assertStringNotContainsString($value, $metadata);
        }
        self::assertSame(900.0, Date::parse((string) $row->created_at)->diffInSeconds(Date::parse((string) $row->expires_at)));
    }

    public function test_wrong_organization_membership_session_and_turn_are_denied(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        [, $other, , $otherSession] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Synthetic']);
        self::assertNull($this->store()->read((string) Str::ulid(), (string) $membership->id, $session, $id));
        self::assertNull($this->store()->read($context->organizationId, (string) $other->id, $session, $id));
        self::assertNull($this->store()->read($context->organizationId, (string) $membership->id, $otherSession, $id));
        self::assertSame(0, $this->store()->deleteSession($context->organizationId, (string) $other->id, $session));
        self::assertSame('active', $this->row($id)->status);
        foreach ([[(string) $other->id, $session, null], [(string) $membership->id, $session, (string) Str::ulid()]] as [$owner, $sessionId, $turn]) {
            try {
                $this->store()->persist($context->organizationId, $owner, $sessionId, $turn, ['reply' => 'Synthetic']);
                self::fail('Mismatched ownership must reject persistence.');
            } catch (ConversationRecoveryUnavailable $exception) {
                self::assertSame('conversation_recovery_unavailable', $exception->getMessage());
            }
        }
        self::assertSame(1, DB::table('conversation_recovery_artifacts')->count());
    }

    public function test_reads_and_replays_never_extend_deadline_and_exact_expiry_removes_body(): void
    {
        Date::setTestNow('2026-09-25T12:00:00Z');
        [, $membership, $context, $session] = $this->fixture();
        config()->set('conversation_recovery.ttl_seconds', 3600);
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Synthetic']);
        $expires = $this->row($id)->expires_at;
        Date::setTestNow('2026-09-25T12:14:59Z');
        self::assertSame(['reply' => 'Synthetic'], $this->store()->read($context->organizationId, (string) $membership->id, $session, $id));
        self::assertSame($expires, $this->row($id)->expires_at);
        Date::setTestNow('2026-09-25T12:15:00Z');
        self::assertNull($this->store()->read($context->organizationId, (string) $membership->id, $session, $id));
        self::assertSame('deleted', $this->row($id)->status);
        Storage::disk(ConversationRecoveryStore::DISK)->assertDirectoryEmpty('/');
    }

    public function test_exit_and_user_logout_cleanup_are_idempotent_and_scoped(): void
    {
        [$user, $membership, $context, $session] = $this->fixture();
        [, $other, , $otherSession] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Mine']);
        $otherId = $this->store()->persist($context->organizationId, (string) $other->id, $otherSession, null, ['reply' => 'Other']);
        self::assertSame(1, $this->store()->deleteSession($context->organizationId, (string) $membership->id, $session));
        self::assertSame(0, $this->store()->deleteSession($context->organizationId, (string) $membership->id, $session));
        self::assertNull($this->store()->read($context->organizationId, (string) $membership->id, $session, $id));
        $again = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Again']);
        $this->store()->deleteForUser((string) $user->id);
        self::assertSame('deleted', $this->row($again)->status);
        self::assertSame(['reply' => 'Other'], $this->store()->read($context->organizationId, (string) $other->id, $otherSession, $otherId));
    }

    public function test_failed_deletion_remains_unreadable_pending_and_retryable(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Synthetic']);
        $disk = Storage::disk(ConversationRecoveryStore::DISK);
        $this->failDeletes();
        try {
            $this->store()->deleteMembership($context->organizationId, (string) $membership->id);
            self::fail('Storage failure must not report completed deletion.');
        } catch (ConversationRecoveryUnavailable $exception) {
            self::assertNull($exception->getPrevious());
        }
        self::assertSame('deletion_pending', $this->row($id)->status);
        self::assertNull($this->row($id)->deleted_at);
        self::assertNull($this->store()->read($context->organizationId, (string) $membership->id, $session, $id));
        self::assertSame(1, $this->store()->purgeExpired()['failed']);
        Storage::set(ConversationRecoveryStore::DISK, $disk);
        self::assertSame(1, $this->store()->purgeExpired()['deleted']);
        self::assertSame('deleted', $this->row($id)->status);
        $disk->assertDirectoryEmpty('/');
    }

    public function test_withdrawal_commits_before_failed_cleanup_and_replay_retries(): void
    {
        [$user, $membership, $context, $session] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Synthetic']);
        $disk = Storage::disk(ConversationRecoveryStore::DISK);
        $this->failDeletes();
        $command = (string) Str::uuid();
        $withdraw = fn (): array => $this->contexts()->run($context, fn (): array => $this->app->make(VoiceConsentService::class)
            ->withdraw($user, VoiceConsentService::PROVIDER, 'en-IN', $command));
        try {
            $withdraw();
            self::fail('Deletion outage must be visible.');
        } catch (ConversationRecoveryUnavailable) {
            self::assertSame('withdrawn', DB::table('voice_consent_events')->where('learner_membership_id', $membership->id)->value('state'));
        }
        self::assertSame('deletion_pending', $this->row($id)->status);
        Storage::set(ConversationRecoveryStore::DISK, $disk);
        $withdraw();
        self::assertSame('deleted', $this->row($id)->status);
        self::assertSame(1, DB::table('voice_consent_events')->where('learner_membership_id', $membership->id)->count());
    }

    public function test_erasure_waits_for_recovery_deletion_and_worker_reconciles(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Private body']);
        $operator = $this->createUser();
        $organization = Organization::query()->findOrFail($context->organizationId);
        [$operatorMembership, $operatorContext] = $this->createMembership($organization, $operator, Membership::TYPE_ORGANIZATION_ADMIN);
        $this->permission($operatorMembership, $operatorContext, 'privacy.erasure.request');
        $disk = Storage::disk(ConversationRecoveryStore::DISK);
        $this->failDeletes();
        $result = $this->contexts()->run($operatorContext, fn (): array => $this->app->make(PrivacyErasureService::class)
            ->create($operator, (string) $membership->id, (string) Str::uuid()));
        self::assertSame('partially_completed', $result['status']);
        self::assertNull($result['completed_at']);
        self::assertSame('deletion_pending', $this->row($id)->status);
        Storage::set(ConversationRecoveryStore::DISK, $disk);
        self::assertSame(0, Artisan::call('conversation:purge-recovery'));
        self::assertSame('completed', DB::table('privacy_erasure_requests')->where('id', $result['erasure_request_id'])->value('status'));
        $disk->assertDirectoryEmpty('/');
    }

    public function test_tampering_is_rejected_and_never_exposes_ciphertext_or_body(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Private body']);
        Storage::disk(ConversationRecoveryStore::DISK)->put((string) $this->row($id)->storage_path, 'tampered-private-bytes');
        try {
            $this->store()->read($context->organizationId, (string) $membership->id, $session, $id);
            self::fail('Tampered ciphertext must be rejected.');
        } catch (ConversationRecoveryUnavailable $exception) {
            self::assertSame('conversation_recovery_unavailable', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame('deleted', $this->row($id)->status);
    }

    public function test_inventory_includes_safe_metadata_not_paths_digests_or_bodies(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Private body']);
        $member = DB::table('memberships')->where('id', $membership->id)->first();
        self::assertInstanceOf(stdClass::class, $member);
        $export = $this->app->make(LearnerPrivacyInventory::class)->export($context->organizationId, $member);
        $json = json_encode($export, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('conversation_recovery_artifacts', $json);
        self::assertStringContainsString('conversation_dialogue_states', $json);
        self::assertStringContainsString('conversation_dialogue_outcomes', $json);
        foreach (['Private body', (string) $this->row($id)->storage_path, (string) $this->row($id)->ciphertext_sha256] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
    }

    public function test_withdrawal_barrier_closes_owned_dialogue_and_prevents_late_context_write(): void
    {
        [$user, $membership, $context, $session] = $this->fixture();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Synthetic']);
        DB::table('conversation_dialogue_states')->insert([
            'organization_id' => $context->organizationId, 'learner_membership_id' => $membership->id,
            'conversation_session_id' => $session, 'flow' => 'question_help', 'state' => 'awaiting_question',
            'start_artifact_id' => $id, 'notice_version' => 'synthetic-notice', 'notice_accepted_at' => now(),
            'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->contexts()->run($context, fn (): array => $this->app->make(VoiceConsentService::class)
            ->withdraw($user, VoiceConsentService::PROVIDER, 'en-IN', (string) Str::uuid()));
        self::assertSame('closed', DB::table('conversation_dialogue_states')->where('conversation_session_id', $session)->value('state'));
        self::assertNull($this->store()->read($context->organizationId, (string) $membership->id, $session, $id));
        $this->expectException(ConversationRecoveryUnavailable::class);
        $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Late provider response']);
    }

    public function test_rolled_back_metadata_leaves_only_unreadable_ciphertext_until_orphan_purge(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        DB::beginTransaction();
        $id = $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Synthetic']);
        DB::rollBack();
        self::assertNull($this->store()->read($context->organizationId, (string) $membership->id, $session, $id));
        self::assertCount(1, Storage::disk(ConversationRecoveryStore::DISK)->files());
        Date::setTestNow(Date::now()->addSeconds(901));
        self::assertSame(1, $this->store()->purgeExpired()['orphan_deleted']);
        Storage::disk(ConversationRecoveryStore::DISK)->assertDirectoryEmpty('/');
    }

    public function test_oversized_payload_and_public_disk_configuration_fail_closed(): void
    {
        [, $membership, $context, $session] = $this->fixture();
        config()->set('conversation_recovery.max_plaintext_bytes', 1);
        try {
            $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Too large']);
            self::fail('Bound must be enforced.');
        } catch (ConversationRecoveryUnavailable) {
            self::assertSame(0, DB::table('conversation_recovery_artifacts')->count());
        }
        config()->set('conversation_recovery.max_plaintext_bytes', 8 * 1024 * 1024);
        config()->set('filesystems.disks.conversation_recovery.serve', true);
        $this->expectException(ConversationRecoveryUnavailable::class);
        $this->store()->persist($context->organizationId, (string) $membership->id, $session, null, ['reply' => 'Synthetic']);
    }

    private function store(): ConversationRecoveryStore
    {
        return $this->app->make(ConversationRecoveryStore::class);
    }

    private function row(string $id): stdClass
    {
        $row = DB::table('conversation_recovery_artifacts')->where('id', $id)->first();
        self::assertInstanceOf(stdClass::class, $row);

        return $row;
    }

    private function failDeletes(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturn(true);
        $disk->method('delete')->willReturn(false);
        $disk->method('files')->willReturn([]);
        Storage::set(ConversationRecoveryStore::DISK, $disk);
    }

    /** @return array{User, Membership, OrganizationContext, string} */
    private function fixture(): array
    {
        $organization = Organization::query()->where('system_key', Organization::DIRECT_SYSTEM_KEY)->firstOrFail();
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership($organization, $user);
        $this->permission($membership, $context, 'learning.participate');
        DB::table('learner_profiles')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $context->organizationId,
            'learner_membership_id' => $membership->id, 'adult_confirmed_at' => now(),
            'onboarding_completed_at' => now(), 'created_at' => now(),
        ]);
        $persona = DB::table('persona_versions')->where('organization_id', $context->organizationId)->first();
        $scenario = DB::table('scenario_versions')->where('organization_id', $context->organizationId)->first();
        self::assertInstanceOf(stdClass::class, $persona);
        self::assertInstanceOf(stdClass::class, $scenario);
        $session = (string) Str::ulid();
        DB::table('conversation_sessions')->insert([
            'id' => $session, 'organization_id' => $context->organizationId, 'learner_membership_id' => $membership->id,
            'persona_id' => $persona->persona_id, 'persona_version_id' => $persona->id, 'persona_definition_hash' => $persona->definition_hash,
            'scenario_id' => $scenario->scenario_id, 'scenario_version_id' => $scenario->id, 'scenario_definition_hash' => $scenario->definition_hash,
            'mode' => 'friend', 'coaching_mode' => 'gentle', 'status' => 'active', 'ui_locale' => 'en-IN',
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $membership, $context, $session];
    }

    private function permission(Membership $membership, OrganizationContext $context, string $permissionKey): void
    {
        $role = $this->createRole($context, 'recovery-'.strtolower((string) Str::ulid()));
        $permission = Permission::query()->where('key', $permissionKey)->firstOrFail();
        DB::table('membership_roles')->insert([
            'organization_id' => $context->organizationId, 'membership_id' => $membership->id,
            'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $context->organizationId, 'role_id' => $role->id,
            'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
