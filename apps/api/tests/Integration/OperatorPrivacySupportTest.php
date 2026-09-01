<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Conversation\ConversationSessionService;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceDeletionService;
use App\Conversation\Voice\VoiceRecordingService;
use App\Conversation\Voice\VoiceSourceObjectStore;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextSource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

final class OperatorPrivacySupportTest extends TenancyIntegrationTestCase
{
    private const string OTP = '246813';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new OperatorPrivacySupportOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new OperatorPrivacySupportNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_operator_privacy_support_is_tenant_isolated(): void
    {
        $user = $this->signInWeb('+919876543221');
        $organization = $this->createOrganization('Privacy Ops Tenant');
        $foreign = $this->createOrganization('Privacy Foreign Tenant');

        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->attachOperatorPermissions(
            $operatorMembership,
            $operatorContext,
            [
                'support.diagnostics.read',
                'privacy.export.request',
                'privacy.erasure.request',
            ],
        );

        $learner = $this->createUser();
        [$learnerMembership] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );

        $foreignLearner = $this->createUser();
        [$foreignMembership] = $this->createMembership(
            $foreign,
            $foreignLearner,
            Membership::TYPE_LEARNER,
        );

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $operatorMembership->getKey(),
            ])
            ->assertOk();

        $diagnostics = $this->getJson(
            '/api/v1/operator/support/diagnostics',
        )
            ->assertOk()
            ->assertJsonStructure([
                'generated_at',
                'items',
            ]);
        $this->assertNoStore($diagnostics);

        $exportKey = (string) Str::uuid();
        $export = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $exportKey,
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath(
                'target_membership_id',
                $learnerMembership->getKey(),
            )
            ->assertJsonPath(
                'summary.inventory_version',
                'learner-privacy-v7',
            )
            ->assertJsonPath(
                'summary.scope.membership_id',
                $learnerMembership->getKey(),
            )
            ->assertJsonPath('status', 'completed');
        $this->assertNoStore($export);
        $exportId = $export->json('export_request_id');
        self::assertIsString($exportId);
        $recordGroups = $export->json('summary.record_groups');
        self::assertIsArray($recordGroups);
        $recordClasses = array_column($recordGroups, 'record_class');
        self::assertContains('users', $recordClasses);
        self::assertContains('password_credentials', $recordClasses);
        self::assertContains('federated_identities', $recordClasses);
        self::assertContains('learner_entitlements', $recordClasses);
        self::assertContains('subscription_checkouts', $recordClasses);
        self::assertContains('live_ai_meter_charges', $recordClasses);
        self::assertContains('password_recovery_challenges', $recordClasses);
        self::assertContains('privileged_password_recovery_cases', $recordClasses);
        self::assertContains('privileged_password_recovery_approvals', $recordClasses);
        self::assertContains('account_recovery_management_events', $recordClasses);
        self::assertContains('account_recovery_notification_events', $recordClasses);
        self::assertContains('password_security_events', $recordClasses);
        self::assertContains('learner_memory_facts', $recordClasses);
        self::assertContains('tuition_requests', $recordClasses);
        self::assertContains('learner_profile_current_goals', $recordClasses);
        self::assertContains('learner_goal_corrections', $recordClasses);
        self::assertContains('organization_administration_events', $recordClasses);
        self::assertContains('voice_source_objects', $recordClasses);
        self::assertContains('client_offline_records', $recordClasses);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $exportKey,
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('export_request_id', $exportId);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $exportKey,
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $foreignMembership->getKey(),
        ])->assertConflict();

        $this->getJson('/api/v1/operator/privacy/exports/'.$exportId)
            ->assertOk()
            ->assertJsonPath('export_request_id', $exportId);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $foreignMembership->getKey(),
        ])->assertNotFound();

        $erasureKey = (string) Str::uuid();
        $erasure = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $erasureKey,
        ])->postJson('/api/v1/operator/privacy/erasures', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath(
                'outcome.inventory_version',
                'learner-privacy-v7',
            )
            ->assertJsonPath('voice_recording_count', 0);
        $this->assertNoStore($erasure);
        $erasureId = $erasure->json('erasure_request_id');
        self::assertIsString($erasureId);

        $this->getJson('/api/v1/operator/privacy/erasures/'.$erasureId)
            ->assertOk()
            ->assertJsonPath('erasure_request_id', $erasureId);

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $erasureKey,
        ])->postJson('/api/v1/operator/privacy/erasures', [
            'target_membership_id' => $foreignMembership->getKey(),
        ])->assertConflict();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/privacy/erasures', [
            'target_membership_id' => $foreignMembership->getKey(),
        ])->assertNotFound();

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->getKey(),
            'action' => 'privacy.export.requested',
            'subject_id' => $exportId,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->getKey(),
            'action' => 'privacy.erasure.requested',
            'subject_id' => $erasureId,
        ]);
        $this->assertDatabaseMissing('privacy_export_requests', [
            'organization_id' => $foreign->getKey(),
        ]);
    }

    public function test_missing_privacy_permission_is_not_found(): void
    {
        $user = $this->signInWeb('+919876543222');
        $organization = $this->createOrganization('Privacy Denied Tenant');
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])
            ->assertOk();

        $this->getJson('/api/v1/operator/support/diagnostics')
            ->assertNotFound();
    }

    public function test_privacy_erasure_reuses_retryable_voice_deletion_and_reconciles_completion(): void
    {
        Storage::fake('local');
        $operator = $this->signInWeb('+919876543223');
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $operatorMembershipId = DB::table('memberships')
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $operator->getKey())
            ->value('id');
        self::assertIsString($operatorMembershipId);
        $operatorContext = new OrganizationContext(
            organizationId: (string) $organization->getKey(),
            actorUserId: (string) $operator->getKey(),
            membershipId: $operatorMembershipId,
            source: OrganizationContextSource::Membership,
        );
        $operatorMembership = $this->contexts()->run(
            $operatorContext,
            static fn (): Membership => Membership::query()->findOrFail(
                $operatorMembershipId,
            ),
        );
        $this->attachOperatorPermissions(
            $operatorMembership,
            $operatorContext,
            ['privacy.export.request', 'privacy.erasure.request'],
        );
        $learner = $this->createUser();
        [$learnerMembership, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
        );
        $this->attachOperatorPermissions(
            $learnerMembership,
            $learnerContext,
            ['learning.participate'],
        );
        $otherOrganization = $this->createOrganization(
            'Privacy Shared Identity Tenant',
        );
        [$otherMembership] = $this->createMembership(
            $otherOrganization,
            $learner,
        );
        $now = now();
        $profileId = (string) Str::ulid();
        DB::table('learner_profiles')->insert([
            'id' => $profileId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $learnerMembership->getKey(),
            'adult_confirmed_at' => $now,
            'onboarding_completed_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('learner_goals')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'learner_profile_id' => $profileId,
            'learner_membership_id' => $learnerMembership->getKey(),
            'goal_key' => 'workplace-confidence',
            'selected_at' => $now,
            'created_at' => $now,
        ]);
        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $learnerMembership->getKey(),
            'cohort_id' => null,
            'curriculum_owner_organization_id' => null,
            'curriculum_version_id' => null,
            'curriculum_publication_id' => null,
            'curriculum_share_grant_id' => null,
            'status' => 'pending_curriculum',
            'enrolled_at' => $now,
            'activated_at' => null,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $memoryFactId = (string) Str::ulid();
        DB::table('learner_memory_facts')->insert([
            'id' => $memoryFactId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $learnerMembership->getKey(),
            'purpose' => 'conversation_context',
            'source' => 'learner_explicit',
            'status' => 'active',
            'fact_text' => 'I work in a hotel.',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $errorPatternId = (string) Str::ulid();
        DB::table('learner_error_patterns')->insert([
            'id' => $errorPatternId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $learnerMembership->getKey(),
            'error_category' => 'article_usage',
            'observation_count' => 2,
            'first_observed_at' => $now,
            'last_observed_at' => $now,
        ]);

        $targetAuthenticationSession = AuthenticationSession::query()
            ->forceCreate([
                'user_id' => $learner->getKey(),
                'storage_session_digest' => hash(
                    'sha256',
                    'privacy-target-session',
                ),
                'storage_session_ciphertext' => Crypt::encryptString(
                    'privacy-target-session',
                ),
                'is_privileged' => false,
                'started_at' => $now,
                'last_seen_at' => $now,
                'idle_expires_at' => $now->copy()->addHour(),
                'absolute_expires_at' => $now->copy()->addDay(),
            ]);
        DB::table('sessions')->insert([
            'id' => 'privacy-target-session',
            'user_id' => $learner->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'synthetic-privacy-test',
            'payload' => 'synthetic-session-payload',
            'last_activity' => $now->getTimestamp(),
        ]);

        $oauthClientId = DB::table('oauth_clients')->value('id');

        if (! is_string($oauthClientId)) {
            $oauthClientId = (string) Str::uuid();
            DB::table('oauth_clients')->insert([
                'id' => $oauthClientId,
                'owner_type' => null,
                'owner_id' => null,
                'name' => 'Synthetic privacy client',
                'secret' => null,
                'provider' => null,
                'redirect_uris' => json_encode(
                    ['com.globalenglish.app://oauth/callback'],
                    JSON_THROW_ON_ERROR,
                ),
                'grant_types' => json_encode(
                    ['authorization_code', 'refresh_token'],
                    JSON_THROW_ON_ERROR,
                ),
                'revoked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $oauthCodeId = str_repeat('c', 80);
        $oauthAccessTokenId = str_repeat('a', 80);
        $oauthRefreshTokenId = str_repeat('r', 80);
        DB::table('oauth_auth_codes')->insert([
            'id' => $oauthCodeId,
            'user_id' => $learner->getKey(),
            'client_id' => $oauthClientId,
            'scopes' => '[]',
            'revoked' => false,
            'expires_at' => $now->copy()->addMinute(),
        ]);
        DB::table('oauth_access_tokens')->insert([
            'id' => $oauthAccessTokenId,
            'user_id' => $learner->getKey(),
            'client_id' => $oauthClientId,
            'name' => null,
            'scopes' => '[]',
            'revoked' => false,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => $now->copy()->addHour(),
        ]);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => $oauthRefreshTokenId,
            'access_token_id' => $oauthAccessTokenId,
            'revoked' => false,
            'expires_at' => $now->copy()->addDay(),
        ]);
        $mobileFamilyId = (string) Str::ulid();
        DB::table('mobile_token_families')->insert([
            'id' => $mobileFamilyId,
            'user_id' => $learner->getKey(),
            'client_id' => $oauthClientId,
            'selected_organization_id' => $organization->getKey(),
            'selected_membership_id' => $learnerMembership->getKey(),
            'installation_digest' => hash('sha256', 'privacy-installation'),
            'last_used_at' => $now,
            'absolute_expires_at' => $now->copy()->addDay(),
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mobile_refresh_tokens')->insert([
            'id' => (string) Str::ulid(),
            'family_id' => $mobileFamilyId,
            'token_digest' => hash('sha256', 'privacy-refresh-token'),
            'passport_refresh_token_id' => $oauthRefreshTokenId,
            'access_token_id' => $oauthAccessTokenId,
            'expires_at' => $now->copy()->addDay(),
            'rotated_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $otherMobileFamilyId = (string) Str::ulid();
        DB::table('mobile_token_families')->insert([
            'id' => $otherMobileFamilyId,
            'user_id' => $learner->getKey(),
            'client_id' => $oauthClientId,
            'selected_organization_id' => $otherOrganization->getKey(),
            'selected_membership_id' => $otherMembership->getKey(),
            'installation_digest' => hash(
                'sha256',
                'privacy-other-installation',
            ),
            'last_used_at' => $now,
            'absolute_expires_at' => $now->copy()->addDay(),
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $foreignAuditId = (string) Str::ulid();
        DB::table('audit_events')->insert([
            'id' => $foreignAuditId,
            'scope_kind' => 'organization',
            'organization_id' => $otherOrganization->getKey(),
            'actor_user_id' => $learner->getKey(),
            'actor_membership_id' => $otherMembership->getKey(),
            'target_organization_id' => null,
            'capability_key' => null,
            'reason_key' => null,
            'action' => 'privacy.synthetic_foreign_action',
            'subject_type' => 'membership',
            'subject_id' => $otherMembership->getKey(),
            'correlation_id' => null,
            'occurred_at' => $now,
        ]);
        $session = $this->contexts()->run(
            $learnerContext,
            fn (): array => $this->app->make(ConversationSessionService::class)
                ->start(
                    learner: $learner,
                    personaKey: 'patient-friend-prototype',
                    scenarioKey: 'everyday-friend-prototype',
                    coachingMode: 'gentle',
                    uiLocale: 'en-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );
        $consents = $this->app->make(VoiceConsentService::class);

        foreach (
            [VoiceConsentService::UPLOAD, VoiceConsentService::PROVIDER] as $purpose
        ) {
            $this->contexts()->run(
                $learnerContext,
                fn (): array => $consents->grant(
                    learner: $learner,
                    purpose: $purpose,
                    requestedLocale: 'en-IN',
                    idempotencyKey: (string) Str::uuid(),
                ),
            );
        }

        $this->app->instance(
            RecordedSpeechToTextProvider::class,
            new PrivacyAlwaysFailRecordedSpeechProvider,
        );
        $recording = $this->contexts()->run(
            $learnerContext,
            fn (): array => $this->app->make(VoiceRecordingService::class)
                ->create(
                    learner: $learner,
                    sessionId: (string) $session['id'],
                    audioBytes: self::wav(),
                    declaredMimeType: 'audio/wav',
                    idempotencyKey: (string) Str::uuid(),
                ),
        );
        $objectKey = DB::table('voice_recordings')
            ->where('id', $recording['id'])
            ->value('source_object_key');
        self::assertIsString($objectKey);

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $operatorMembership->getKey(),
            ])
            ->assertOk();
        $privacyExport = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/privacy/exports', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])->assertCreated();
        $priorExportId = $privacyExport->json('export_request_id');
        self::assertIsString($priorExportId);
        $exportGroups = $privacyExport->json('summary.record_groups');
        self::assertIsArray($exportGroups);
        $memoryGroup = self::recordGroup(
            $exportGroups,
            'learner_memory_facts',
        );
        $memoryRecords = $memoryGroup['records'] ?? null;
        self::assertIsArray($memoryRecords);
        self::assertSame(
            'I work in a hotel.',
            $memoryRecords[0]['attributes']['fact_text'] ?? null,
        );
        $encodedExport = json_encode(
            $privacyExport->json('summary'),
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString($objectKey, $encodedExport);
        self::assertStringNotContainsString('source_sha256', $encodedExport);
        self::assertStringNotContainsString(
            'transcript_ciphertext',
            $encodedExport,
        );
        self::assertStringNotContainsString(
            (string) $otherOrganization->getKey(),
            $encodedExport,
        );
        self::assertStringNotContainsString(
            (string) $otherMembership->getKey(),
            $encodedExport,
        );
        self::assertStringNotContainsString($foreignAuditId, $encodedExport);

        $store = new PrivacyRetryVoiceSourceObjectStore;
        $this->app->instance(VoiceSourceObjectStore::class, $store);
        $ambientTransactionLevel = DB::transactionLevel();
        $idempotencyKey = (string) Str::uuid();
        $erasure = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/operator/privacy/erasures', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'partially_completed')
            ->assertJsonPath(
                'outcome.scope.membership_id',
                $learnerMembership->getKey(),
            )
            ->assertJsonPath('voice_recording_count', 1)
            ->assertJsonPath('voice_deletion_completed_count', 0);
        $erasureId = $erasure->json('erasure_request_id');
        self::assertIsString($erasureId);
        $outcomes = $erasure->json('outcome.record_outcomes');
        self::assertIsArray($outcomes);
        self::assertSame(
            'redact_fact_text_and_mark_deleted',
            self::recordOutcome(
                $outcomes,
                'learner_memory_facts',
            )['action'] ?? null,
        );
        self::assertSame(
            'retain_immutable_conversation_evidence',
            self::recordOutcome(
                $outcomes,
                'conversation_sessions',
            )['action'] ?? null,
        );

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $organization->getKey(),
            'id' => $learnerMembership->getKey(),
            'status' => Membership::STATUS_ENDED,
        ]);
        $this->assertDatabaseHas('memberships', [
            'organization_id' => $otherOrganization->getKey(),
            'id' => $otherMembership->getKey(),
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $learner->getKey(),
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('membership_roles', [
            'organization_id' => $organization->getKey(),
            'membership_id' => $learnerMembership->getKey(),
        ]);
        $this->assertDatabaseHas('enrollments', [
            'organization_id' => $organization->getKey(),
            'id' => $enrollmentId,
            'status' => 'withdrawn',
        ]);
        $this->assertDatabaseHas('learner_profiles', [
            'id' => $profileId,
        ]);
        $this->assertDatabaseHas('learner_goals', [
            'learner_profile_id' => $profileId,
        ]);
        $this->assertDatabaseHas('learner_memory_facts', [
            'id' => $memoryFactId,
            'status' => 'deleted',
            'fact_text' => '[privacy-erased]',
        ]);
        $this->assertDatabaseHas('memory_events', [
            'memory_fact_id' => $memoryFactId,
            'actor_user_id' => $operator->getKey(),
            'event_code' => 'deleted',
        ]);
        $this->assertDatabaseMissing('learner_error_patterns', [
            'id' => $errorPatternId,
        ]);
        $this->assertDatabaseHas('conversation_sessions', [
            'id' => $session['id'],
            'learner_membership_id' => $learnerMembership->getKey(),
        ]);
        $this->assertDatabaseMissing('sessions', [
            'id' => 'privacy-target-session',
        ]);
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $targetAuthenticationSession->getKey(),
            'revocation_reason' => 'privacy_erasure',
        ]);
        $this->assertDatabaseHas('mobile_token_families', [
            'id' => $mobileFamilyId,
            'selected_organization_id' => null,
            'selected_membership_id' => null,
            'revocation_reason' => 'privacy_erasure',
        ]);
        $this->assertDatabaseHas('mobile_token_families', [
            'id' => $otherMobileFamilyId,
            'selected_organization_id' => $otherOrganization->getKey(),
            'selected_membership_id' => $otherMembership->getKey(),
            'revocation_reason' => 'privacy_erasure',
        ]);
        $this->assertDatabaseHas('oauth_auth_codes', [
            'id' => $oauthCodeId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $oauthAccessTokenId,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $oauthRefreshTokenId,
            'revoked' => true,
        ]);
        $redactedSummary = DB::table('privacy_export_requests')
            ->where('id', $priorExportId)
            ->value('summary_json');
        if (is_string($redactedSummary)) {
            $redactedSummary = json_decode(
                $redactedSummary,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        }
        self::assertIsArray($redactedSummary);
        self::assertSame([], $redactedSummary['record_groups'] ?? null);
        self::assertArrayHasKey('privacy_erased_at', $redactedSummary);

        $this->assertDatabaseHas('voice_recordings', [
            'id' => $recording['id'],
            'status' => 'provider_failed_recoverable',
            'source_object_key' => $objectKey,
            'source_deleted_at' => null,
            'transcript_ciphertext' => null,
        ]);
        $deletion = DB::table('voice_deletion_requests')->sole();
        $this->assertDatabaseHas('privacy_erasure_voice_deletions', [
            'organization_id' => $organization->getKey(),
            'privacy_erasure_request_id' => $erasureId,
            'voice_deletion_request_id' => $deletion->id,
        ]);
        $outbox = DB::table('outbox_events')
            ->where('aggregate_type', 'voice_deletion_request')
            ->sole();
        $this->assertSame(1, (int) $outbox->attempts);
        $this->assertSame('voice_source_delete_failed', $outbox->last_error_code);
        $this->assertSame(
            [$ambientTransactionLevel],
            $store->transactionLevels,
        );

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/operator/privacy/erasures', [
            'target_membership_id' => $learnerMembership->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('erasure_request_id', $erasureId)
            ->assertJsonPath('status', 'partially_completed');
        $this->assertSame(1, $store->attempts);
        $this->assertDatabaseCount('privacy_erasure_requests', 1);
        $this->assertDatabaseCount('voice_deletion_requests', 1);
        $this->assertDatabaseCount('privacy_erasure_voice_deletions', 1);

        $store->fail = false;
        DB::table('outbox_events')
            ->where('id', $outbox->id)
            ->update(['available_at' => now()->subSecond()]);
        $processed = $this->app->make(VoiceDeletionService::class)->processDue();
        $this->assertSame(
            ['attempted' => 1, 'succeeded' => 1, 'failed' => 0],
            $processed,
        );
        Storage::disk('local')->assertMissing($objectKey);
        $this->getJson('/api/v1/operator/privacy/erasures/'.$erasureId)
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('voice_recording_count', 1)
            ->assertJsonPath('voice_deletion_completed_count', 1);
        $this->assertDatabaseHas('voice_recordings', [
            'id' => $recording['id'],
            'status' => 'withdrawn_deleted',
            'source_object_key' => null,
        ]);
    }

    private function signInWeb(string $mobileNumber): User
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());

        $userId = $verified->json('user.id');
        $sessionId = $verified->json('session.id');
        self::assertIsString($userId);
        self::assertIsString($sessionId);

        $this->markTestAuthenticationSessionPrivileged(
            $sessionId,
            $userId,
            now(),
        );

        return User::query()->whereKey($userId)->sole();
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function attachOperatorPermissions(
        Membership $membership,
        OrganizationContext $context,
        array $permissionKeys,
    ): void {
        $this->grantTestPermissions(
            $membership,
            $context,
            $permissionKeys,
            'privacy',
        );
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    /**
     * @param  array<mixed>  $groups
     * @return array<mixed>
     */
    private static function recordGroup(
        array $groups,
        string $recordClass,
    ): array {
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            if (($group['record_class'] ?? null) === $recordClass) {
                return $group;
            }
        }

        self::fail(sprintf('Missing privacy record group %s.', $recordClass));
    }

    /**
     * @param  array<mixed>  $outcomes
     * @return array<mixed>
     */
    private static function recordOutcome(
        array $outcomes,
        string $recordClass,
    ): array {
        foreach ($outcomes as $outcome) {
            if (! is_array($outcome)) {
                continue;
            }

            if (($outcome['record_class'] ?? null) === $recordClass) {
                return $outcome;
            }
        }

        self::fail(sprintf('Missing privacy record outcome %s.', $recordClass));
    }

    private static function wav(): string
    {
        $sampleRate = 16_000;
        $dataSize = 3_200;
        $format = 'fmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16);
        $data = 'data'.pack('V', $dataSize).str_repeat("\0", $dataSize);
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}

final class OperatorPrivacySupportOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class OperatorPrivacySupportNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {}
}

final class PrivacyAlwaysFailRecordedSpeechProvider implements RecordedSpeechToTextProvider
{
    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        throw new ConversationProviderException(
            safeCode: 'synthetic_privacy_outage',
            providerKey: 'deterministic-privacy-failure',
            modelVersion: 'privacy-failure-v1',
            latencyMilliseconds: 2,
            inputUnits: strlen($request->audioBytes),
            retryable: true,
        );
    }
}

final class PrivacyRetryVoiceSourceObjectStore implements VoiceSourceObjectStore
{
    public int $attempts = 0;

    public bool $fail = true;

    /** @var list<int> */
    public array $transactionLevels = [];

    public function delete(string $objectKey): bool
    {
        $this->attempts++;
        $this->transactionLevels[] = DB::transactionLevel();

        return ! $this->fail && Storage::disk('local')->delete($objectKey);
    }
}
