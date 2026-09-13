<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\LessonDrillContext;
use App\Curriculum\CanonicalJson;
use App\Curriculum\LessonPractice\LessonPracticeAttachmentService;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Entitlements\LearnerSubscriptionService;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LevelSessions\LevelSessionDeliveryService;
use App\Models\LessonPracticeBlueprint;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class LevelSessionDeliveryTest extends TenancyIntegrationTestCase
{
    public function test_completion_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('level_session_completions'));
        self::assertTrue(Schema::hasTable('level_session_check_submissions'));
        self::assertTrue(Schema::hasTable('level_unlock_decisions'));
        self::assertTrue(Schema::hasTable('level_checkpoint_attempts'));
    }

    public function test_attached_level_one_is_available_and_check_scores_deterministically(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $author = $this->createUser();
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $authorMembership,
            $authorContext,
            ['curriculum.author', 'media.metadata.manage'],
            'lesson-practice',
        );
        $learner = $this->createUser();
        [$learnerMembership, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $this->grantTestPermissions(
            $learnerMembership,
            $learnerContext,
            ['learning.participate'],
            'lesson-practice-learner',
        );
        $this->grantDemoEntitlement($learner, $learnerContext);

        $canonical = $this->canonicalBlueprint();
        $hash = CanonicalJson::sha256($canonical);
        $programId = $this->insertProgram(
            (string) $organization->getKey(),
            (string) $authorContext->membershipId,
        );

        $attachment = app(LessonPracticeAttachmentService::class);
        $created = $this->contexts()->run(
            $authorContext,
            fn (): array => $attachment->createBlueprint(
                $authorContext,
                $programId,
                (string) Str::ulid(),
                'can',
                $canonical,
                (string) Str::ulid(),
            ),
        );
        $blueprintId = (string) $created['blueprint']->getKey();
        $this->contexts()->run(
            $authorContext,
            function () use ($attachment, $authorContext, $blueprintId, $hash): void {
                $attachment->recordReview(
                    $authorContext,
                    $blueprintId,
                    'english_academic',
                    'approved',
                    $hash,
                    (string) Str::ulid(),
                );
                $attachment->recordReview(
                    $authorContext,
                    $blueprintId,
                    'kannada',
                    'approved',
                    $hash,
                    (string) Str::ulid(),
                );
            },
        );

        $parts = [];
        [$appointmentId, $appointmentEventId] = $this->insertRightsAppointment(
            (string) $organization->getKey(),
            $programId,
            (string) $author->getKey(),
            (string) $authorContext->membershipId,
        );
        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $index => $partId) {
            $parts[] = $this->insertLessonAudioPart(
                (string) $organization->getKey(),
                $programId,
                (string) $authorContext->membershipId,
                $partId,
                $index + 1,
                $appointmentId,
                $appointmentEventId,
            );
        }
        $this->contexts()->run(
            $authorContext,
            fn (): array => $attachment->attachParts(
                $authorContext,
                $blueprintId,
                $hash,
                $parts,
                (string) Str::ulid(),
            ),
        );
        $published = $this->contexts()->run(
            $authorContext,
            fn (): array => $attachment->publishBlueprint(
                $authorContext,
                $blueprintId,
                $hash,
                'wave71-test-can',
            ),
        );
        self::assertTrue($published['created']);
        self::assertSame('published', $published['blueprint']->state);
        $replayed = $this->contexts()->run(
            $authorContext,
            fn (): array => $attachment->publishBlueprint(
                $authorContext,
                $blueprintId,
                $hash,
                'wave71-test-can',
            ),
        );
        self::assertFalse($replayed['created']);

        $delivery = app(LevelSessionDeliveryService::class);
        $levels = $this->contexts()->run(
            $learnerContext,
            fn (): array => $delivery->listLevels($learnerContext, $learner),
        );
        self::assertSame('available', $levels[0]['availability']);
        self::assertSame('can', $levels[0]['concept_key']);
        self::assertSame('allowed', $levels[0]['progress_access']);
        self::assertSame('commissioning_pending', $levels[1]['availability']);
        self::assertSame('not_applicable', $levels[1]['progress_access']);

        $detail = $this->contexts()->run(
            $learnerContext,
            fn (): array => $delivery->sessionDetail(
                $learnerContext,
                $learner,
                1,
                'practice',
            ),
        );
        self::assertSame('practice', $detail['part_id']);
        self::assertSame(
            '/api/v1/me/levels/1/sessions/practice/audio',
            $detail['audio_stream_path'],
        );
        self::assertArrayNotHasKey('correct_option', $detail['check']['items'][0]);

        $publishedBlueprint = LessonPracticeBlueprint::query()
            ->findOrFail($blueprintId);
        $canonical = $publishedBlueprint->canonical_json;
        $introductionDrill = LessonDrillContext::fromCanonical(
            1,
            'introduction',
            'can',
            $canonical,
        );
        self::assertNotNull($introductionDrill);
        self::assertSame(['I can + base verb.'], $introductionDrill->targetPatterns);
        self::assertNull(
            LessonDrillContext::fromCanonical(1, 'conversation', 'can', $canonical),
        );

        try {
            $this->contexts()->run(
                $learnerContext,
                fn (): array => $delivery->completeSession(
                    $learnerContext,
                    $learner,
                    1,
                    'conversation',
                    (string) Str::ulid(),
                ),
            );
            self::fail('conversation completed without introduction');
        } catch (LearningOperationRejected $exception) {
            self::assertSame('session_locked', $exception->getMessage());
        }

        try {
            $this->contexts()->run(
                $learnerContext,
                fn (): array => $delivery->submitCheck(
                    $learnerContext,
                    $learner,
                    1,
                    'practice',
                    ['can-01' => 0],
                    (string) Str::ulid(),
                ),
            );
            self::fail('practice check accepted without introduction');
        } catch (LearningOperationRejected $exception) {
            self::assertSame('session_locked', $exception->getMessage());
        }

        $completion = $this->contexts()->run(
            $learnerContext,
            fn (): array => $delivery->completeSession(
                $learnerContext,
                $learner,
                1,
                'introduction',
                (string) Str::ulid(),
            ),
        );
        self::assertSame('introduction', $completion['part_id']);
        self::assertFalse($completion['replayed']);

        $check = $this->contexts()->run(
            $learnerContext,
            fn (): array => $delivery->submitCheck(
                $learnerContext,
                $learner,
                1,
                'practice',
                [
                    'can-01' => 0,
                    'can-02' => 0,
                    'can-03' => 0,
                ],
                (string) Str::ulid(),
            ),
        );
        self::assertTrue($check['passed']);
        self::assertSame(100, $check['score']);

        try {
            $this->contexts()->run(
                $learnerContext,
                fn (): array => $delivery->sessionDetail(
                    $learnerContext,
                    $learner,
                    1,
                    'conversation',
                ),
            );
            self::fail('conversation detail allowed on demo plan');
        } catch (EntitlementDenied $exception) {
            self::assertSame(EntitlementDenied::SESSION_LOCKED, $exception->messageKey);
        }
    }

    public function test_other_organization_does_not_see_attached_level(): void
    {
        $other = $this->createOrganization('Other org for levels');
        $learner = $this->createUser();
        [, $context] = $this->createMembership($other, $learner);
        $delivery = app(LevelSessionDeliveryService::class);
        $levels = $this->contexts()->run(
            $context,
            fn (): array => $delivery->listLevels($context, $learner),
        );
        self::assertSame('commissioning_pending', $levels[0]['availability']);
    }

    private function grantDemoEntitlement(
        User $learner,
        OrganizationContext $context,
    ): void {
        $planId = DB::table('entitlement_plans')
            ->where('organization_id', $context->organizationId)
            ->where('code', DirectPlanDefaults::DEMO)
            ->value('id');
        self::assertIsString($planId);
        $this->contexts()->run(
            $context,
            fn (): array => app(LearnerSubscriptionService::class)->checkout(
                $learner,
                $planId,
                (string) Str::ulid(),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalBlueprint(): array
    {
        return [
            'schema' => 'global-english.lesson-practice-loop.v1',
            'lesson' => [
                'level_label' => 'Level 1',
                'concept_key' => 'can',
                'objective' => 'Use can and cannot.',
                'communication_function' => 'ability_and_permission',
                'target_patterns' => ['I can + base verb.'],
                'misconception_codes' => ['CAN_BASE_VERB'],
            ],
            'ordered_parts' => [
                ['order' => 1, 'part_id' => 'introduction'],
                ['order' => 2, 'part_id' => 'practice'],
                ['order' => 3, 'part_id' => 'conversation'],
                ['order' => 4, 'part_id' => 'interaction'],
            ],
            'talk' => [
                'scenario' => 'Ask for permission.',
                'elicitation_goals' => ['Ask one Can I...? question.'],
                'fallback_turns' => ['Can I use your pen?'],
            ],
            'check' => [
                'pass_threshold' => 80,
                'items' => [
                    [
                        'id' => 'can-01',
                        'weight' => 35,
                        'prompt' => 'Choose the correct sentence.',
                        'options' => ['I can speak English.', 'I can speaking English.', 'I can to speak English.'],
                        'correct_option' => 0,
                        'rationale' => 'Can is followed by the base verb.',
                    ],
                    [
                        'id' => 'can-02',
                        'weight' => 30,
                        'prompt' => 'Choose the permission question.',
                        'options' => ['Can I use your pen?', 'I can use your pen?', 'Can I using your pen?'],
                        'correct_option' => 0,
                        'rationale' => 'Permission questions use Can I + base verb.',
                    ],
                    [
                        'id' => 'can-03',
                        'weight' => 35,
                        'prompt' => 'Choose the answer.',
                        'options' => ['Yes, you can.', 'Yes, you can to.', 'Yes, you can sitting.'],
                        'correct_option' => 0,
                        'rationale' => 'Short permission answer uses can.',
                    ],
                ],
            ],
            'apply' => [
                'task' => 'State one ability and ask permission.',
                'rubric' => ['Uses one accurate I can statement'],
                'evidence_state' => 'not_recorded',
            ],
            'kannada_guidance' => [
                'summary' => 'ಈ ಪಾಠದಲ್ಲಿ can ಬಳಕೆಯನ್ನು ಕಲಿಯಿರಿ.',
            ],
            'publication' => [
                'eligible' => true,
                'blockers' => [],
            ],
        ];
    }

    private function insertProgram(string $organizationId, string $membershipId): string
    {
        $programId = (string) Str::ulid();
        $now = CarbonImmutable::now();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'level-session-test-'.$programId,
            'internal_name' => 'Level session test',
            'created_by_membership_id' => $membershipId,
            'created_by_attribution' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $programId;
    }

    /**
     * @return array{
     *   order: int,
     *   part_id: string,
     *   asset_id: string,
     *   metadata_revision_id: string,
     *   metadata_content_hash: string,
     *   rights_revision_id: string
     * }
     */
    private function insertLessonAudioPart(
        string $organizationId,
        string $programId,
        string $membershipId,
        string $partId,
        int $order,
        string $appointmentId,
        string $appointmentEventId,
    ): array {
        $mediaAssetId = (string) Str::ulid();
        $scanId = (string) Str::ulid();
        $rightsId = (string) Str::ulid();
        $metadataId = (string) Str::ulid();
        $sourceHash = hash('sha256', 'level-session-'.$partId);
        $now = CarbonImmutable::now();
        DB::table('media_assets')->insert([
            'id' => $mediaAssetId,
            'organization_id' => $organizationId,
            'key' => 'level-session-'.$partId.'-'.$mediaAssetId,
            'asset_kind' => 'lesson_audio',
            'storage_object_key' => 'private/'.$partId.'.mp3',
            'mime_type' => 'audio/mpeg',
            'byte_size' => 26,
            'asset_hash' => $sourceHash,
            'uploaded_by_membership_id' => $membershipId,
            'uploader_attribution' => 'test',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_security_scans')->insert([
            'id' => $scanId,
            'organization_id' => $organizationId,
            'media_upload_id' => null,
            'media_asset_id' => $mediaAssetId,
            'asset_kind' => 'lesson_audio',
            'object_hash' => $sourceHash,
            'byte_size' => 26,
            'inspection_outcome' => 'passed',
            'malware_outcome' => 'clean',
            'detected_mime_type' => 'audio/mpeg',
            'duration_milliseconds' => 2000,
            'inspector_name' => 'test',
            'inspector_policy_version' => 'test-v1',
            'scanner_name' => 'test',
            'scanner_engine_version' => 'test',
            'scanner_policy_version' => 'test-v1',
            'technical_metadata_hash' => hash('sha256', 'technical-'.$partId),
            'inspected_at' => $now,
            'scanned_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_lifecycles')->insert([
            'organization_id' => $organizationId,
            'media_asset_id' => $mediaAssetId,
            'asset_kind' => 'lesson_audio',
            'state' => 'active',
            'version' => 1,
            'selected_scan_id' => $scanId,
            'predecessor_media_asset_id' => null,
            'managed_by_membership_id' => $membershipId,
            'retired_by_membership_id' => null,
            'retirement_reason_key' => null,
            'managed_at' => $now,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_rights_revisions')->insert([
            'id' => $rightsId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'media_asset_id' => $mediaAssetId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'evidence_status' => 'approved',
            'evidence_hash' => hash('sha256', 'rights-'.$partId),
            'selected_asset_hash' => $sourceHash,
            'rights_holder_reference' => 'test-owner',
            'provenance_reference' => 'test-provenance',
            'rights_basis_key' => 'owner_authorization',
            'license_or_contract_reference' => 'test-contract',
            'permitted_release_channels' => json_encode(['web', 'android'], JSON_THROW_ON_ERROR),
            'permitted_territories' => json_encode(['IN'], JSON_THROW_ON_ERROR),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => false,
            'derivative_use_allowed' => true,
            'effective_at' => $now->subDay(),
            'expires_at' => null,
            'retention_policy_key' => 'preserve_while_referenced',
            'deletion_constraint_key' => 'rights_review_required',
            'confirmed_by_membership_id' => $membershipId,
            'rights_appointment_id' => $appointmentId,
            'rights_appointment_event_id' => $appointmentEventId,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'order' => $order,
            'part_id' => $partId,
            'asset_id' => $mediaAssetId,
            'metadata_revision_id' => $metadataId,
            'metadata_content_hash' => hash('sha256', 'metadata-'.$partId),
            'rights_revision_id' => $rightsId,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function insertRightsAppointment(
        string $organizationId,
        string $programId,
        string $userId,
        string $membershipId,
    ): array {
        $appointmentId = (string) Str::ulid();
        $appointedEventId = (string) Str::ulid();
        $acceptedEventId = (string) Str::ulid();
        $now = CarbonImmutable::now();
        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organizationId,
            'appointee_user_id' => $userId,
            'appointee_membership_id' => $membershipId,
            'authority_kind' => 'rights_confirmer',
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => $now->subDay(),
            'valid_until' => $now->addMonth(),
            'qualification_basis' => 'Synthetic level-session rights fixture',
            'approval_evidence_key' => 'APP-0061',
            'appointed_by_membership_id' => $membershipId,
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            [
                'id' => $appointedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'appointed',
                'actor_membership_id' => $membershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => $now->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $acceptedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $membershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => $now->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [$appointmentId, $acceptedEventId];
    }
}
