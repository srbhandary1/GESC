<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\Authoring\ContentAuthoringCommands;
use App\Curriculum\Authoring\ContentRevisionAppendResult;
use App\Curriculum\Authoring\LocalizedVariantAppendResult;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContentAuthoringCommandsTest extends TenancyIntegrationTestCase
{
    public function test_source_revisions_are_governed_append_only_and_replay_safe(): void
    {
        $organization = $this->createOrganization('Authoring Tenant');
        [$author, $authorMembership, $authorContext] =
            $this->actorWithPermission(
                $organization,
                'curriculum.author',
            );
        [$otherAuthor, , $otherAuthorContext] = $this->actorWithPermission(
            $organization,
            'curriculum.author',
        );
        $draft = $this->createDraft(
            (string) $organization->getKey(),
            (string) $authorMembership->getKey(),
        );
        $commands = $this->app->make(ContentAuthoringCommands::class);
        $firstPayload = [
            'instruction' => 'Synthetic English instruction.',
            'purpose' => 'Authoring command integration fixture.',
        ];
        $first = $this->contexts()->run(
            $authorContext,
            fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                actor: $author,
                curriculumVersionId: $draft['curriculum_version_id'],
                contentDocumentId: $draft['content_document_id'],
                expectedHeadRevisionId: null,
                expectedRevisionNumber: 0,
                contentType: 'instruction',
                learnerVisible: true,
                localizationRequirement: 'kn_required',
                payload: $firstPayload,
                idempotencyKey: 'append-source-v1',
                authorAttribution: 'synthetic-test-author',
            ),
        );
        $replayed = $this->contexts()->run(
            $authorContext,
            fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                actor: $author,
                curriculumVersionId: $draft['curriculum_version_id'],
                contentDocumentId: $draft['content_document_id'],
                expectedHeadRevisionId: null,
                expectedRevisionNumber: 0,
                contentType: 'instruction',
                learnerVisible: true,
                localizationRequirement: 'kn_required',
                payload: $firstPayload,
                idempotencyKey: 'append-source-v1',
                authorAttribution: 'synthetic-test-author',
            ),
        );

        self::assertFalse($first->replayed);
        self::assertTrue($replayed->replayed);
        self::assertSame($first->contentRevisionId, $replayed->contentRevisionId);
        self::assertSame(1, $first->revisionNumber);
        self::assertNull($first->parentRevisionId);
        self::assertSame(
            CanonicalJson::sha256($firstPayload),
            $first->contentHash,
        );
        $this->assertDatabaseHas('content_revisions', [
            'id' => $first->contentRevisionId,
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $draft['curriculum_version_id'],
            'content_document_id' => $draft['content_document_id'],
            'revision_number' => 1,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'authored_by_membership_id' => $authorMembership->getKey(),
        ]);
        self::assertSame(
            1,
            DB::table('content_revisions')
                ->where('organization_id', $organization->getKey())
                ->where(
                    'curriculum_version_id',
                    $draft['curriculum_version_id'],
                )
                ->where(
                    'content_document_id',
                    $draft['content_document_id'],
                )
                ->count(),
        );
        $this->assertDatabaseHas('content_revision_heads', [
            'organization_id' => $organization->getKey(),
            'content_document_id' => $draft['content_document_id'],
            'content_revision_id' => $first->contentRevisionId,
        ]);

        $this->assertIdempotencyConflict(
            fn (): ContentRevisionAppendResult => $this->contexts()->run(
                $authorContext,
                fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                    actor: $author,
                    curriculumVersionId: $draft['curriculum_version_id'],
                    contentDocumentId: $draft['content_document_id'],
                    expectedHeadRevisionId: null,
                    expectedRevisionNumber: 0,
                    contentType: 'instruction',
                    learnerVisible: true,
                    localizationRequirement: 'kn_required',
                    payload: ['instruction' => 'Conflicting replay.'],
                    idempotencyKey: 'append-source-v1',
                    authorAttribution: 'synthetic-test-author',
                ),
            ),
        );
        $this->assertRejected(
            fn (): ContentRevisionAppendResult => $this->contexts()->run(
                $otherAuthorContext,
                fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                    actor: $otherAuthor,
                    curriculumVersionId: $draft['curriculum_version_id'],
                    contentDocumentId: $draft['content_document_id'],
                    expectedHeadRevisionId: null,
                    expectedRevisionNumber: 0,
                    contentType: 'instruction',
                    learnerVisible: true,
                    localizationRequirement: 'kn_required',
                    payload: $firstPayload,
                    idempotencyKey: 'append-source-v1',
                    authorAttribution: 'synthetic-test-author',
                ),
            ),
        );

        $secondPayload = [
            'dialogue' => 'Hello. My name is Asha.',
        ];
        $second = $this->contexts()->run(
            $authorContext,
            fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                actor: $author,
                curriculumVersionId: $draft['curriculum_version_id'],
                contentDocumentId: $draft['content_document_id'],
                expectedHeadRevisionId: $first->contentRevisionId,
                expectedRevisionNumber: 1,
                contentType: 'dialogue',
                learnerVisible: true,
                localizationRequirement: 'english_only_pedagogical',
                payload: $secondPayload,
                idempotencyKey: 'append-source-v2',
                authorAttribution: 'synthetic-test-author',
            ),
        );

        self::assertSame(2, $second->revisionNumber);
        self::assertSame(
            $first->contentRevisionId,
            $second->parentRevisionId,
        );
        $this->assertDatabaseHas('content_revision_heads', [
            'organization_id' => $organization->getKey(),
            'content_document_id' => $draft['content_document_id'],
            'content_revision_id' => $second->contentRevisionId,
        ]);
        $this->assertRejected(
            fn (): ContentRevisionAppendResult => $this->contexts()->run(
                $authorContext,
                fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                    actor: $author,
                    curriculumVersionId: $draft['curriculum_version_id'],
                    contentDocumentId: $draft['content_document_id'],
                    expectedHeadRevisionId: $first->contentRevisionId,
                    expectedRevisionNumber: 1,
                    contentType: 'dialogue',
                    learnerVisible: true,
                    localizationRequirement: 'english_only_pedagogical',
                    payload: ['dialogue' => 'Stale edit.'],
                    idempotencyKey: 'append-source-stale',
                    authorAttribution: 'synthetic-test-author',
                ),
            ),
        );
        $this->assertRejected(
            fn (): ContentRevisionAppendResult => $this->contexts()->run(
                $authorContext,
                fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                    actor: $author,
                    curriculumVersionId: $draft['curriculum_version_id'],
                    contentDocumentId: $draft['content_document_id'],
                    expectedHeadRevisionId: $second->contentRevisionId,
                    expectedRevisionNumber: 2,
                    contentType: 'instruction',
                    learnerVisible: true,
                    localizationRequirement: 'english_only_pedagogical',
                    payload: ['instruction' => 'Must have Kannada.'],
                    idempotencyKey: 'invalid-classification',
                    authorAttribution: 'synthetic-test-author',
                ),
            ),
        );

        $outbox = DB::table('outbox_events')
            ->where(
                'event_type',
                'curriculum.content_revision.appended',
            )
            ->where('aggregate_id', $first->contentRevisionId)
            ->sole(['payload']);
        $outboxPayload = $this->jsonObject($outbox->payload);

        self::assertSame(
            $first->contentRevisionId,
            $outboxPayload['source_revision_id'] ?? null,
        );
        self::assertStringNotContainsString(
            'Synthetic English instruction',
            CanonicalJson::encode($outboxPayload),
        );
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->getKey(),
            'action' => 'curriculum.content_revision.appended',
            'subject_type' => 'content_revision',
            'subject_id' => $first->contentRevisionId,
        ]);
        $this->assertAppendOnlyMutationRejected(
            'content_revisions',
            $first->contentRevisionId,
            ['author_attribution' => 'forged-attribution'],
        );
    }

    public function test_kannada_variants_bind_exact_source_and_translation_heads(): void
    {
        $organization = $this->createOrganization('Translation Tenant');
        [$author, $authorMembership, $authorContext] =
            $this->actorWithPermission(
                $organization,
                'curriculum.author',
            );
        [$translator, $translatorMembership, $translatorContext] =
            $this->actorWithPermission(
                $organization,
                'curriculum.translate.kn',
            );
        $draft = $this->createDraft(
            (string) $organization->getKey(),
            (string) $authorMembership->getKey(),
        );
        $commands = $this->app->make(ContentAuthoringCommands::class);
        $source = $this->contexts()->run(
            $authorContext,
            fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                actor: $author,
                curriculumVersionId: $draft['curriculum_version_id'],
                contentDocumentId: $draft['content_document_id'],
                expectedHeadRevisionId: null,
                expectedRevisionNumber: 0,
                contentType: 'instruction',
                learnerVisible: true,
                localizationRequirement: 'kn_required',
                payload: ['instruction' => 'Say your name.'],
                idempotencyKey: 'source-for-kannada-v1',
                authorAttribution: 'synthetic-test-author',
            ),
        );
        $firstPayload = ['instruction' => 'ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.'];
        $first = $this->contexts()->run(
            $translatorContext,
            fn (): LocalizedVariantAppendResult => $commands->appendKannadaVariant(
                actor: $translator,
                curriculumVersionId: $draft['curriculum_version_id'],
                sourceRevisionId: $source->contentRevisionId,
                sourceContentHash: $source->contentHash,
                expectedSourceRevisionNumber: 1,
                expectedHeadVariantId: null,
                expectedVariantRevisionNumber: 0,
                payload: $firstPayload,
                idempotencyKey: 'append-kannada-v1',
                translatorAttribution: 'synthetic-test-translator',
            ),
        );
        $replayed = $this->contexts()->run(
            $translatorContext,
            fn (): LocalizedVariantAppendResult => $commands->appendKannadaVariant(
                actor: $translator,
                curriculumVersionId: $draft['curriculum_version_id'],
                sourceRevisionId: $source->contentRevisionId,
                sourceContentHash: $source->contentHash,
                expectedSourceRevisionNumber: 1,
                expectedHeadVariantId: null,
                expectedVariantRevisionNumber: 0,
                payload: $firstPayload,
                idempotencyKey: 'append-kannada-v1',
                translatorAttribution: 'synthetic-test-translator',
            ),
        );

        self::assertFalse($first->replayed);
        self::assertTrue($replayed->replayed);
        self::assertSame(
            $first->localizedVariantId,
            $replayed->localizedVariantId,
        );
        self::assertSame($source->contentHash, $first->sourceContentHash);
        self::assertSame(
            CanonicalJson::sha256($firstPayload),
            $first->contentHash,
        );
        $this->assertDatabaseHas('localized_variants', [
            'id' => $first->localizedVariantId,
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $draft['curriculum_version_id'],
            'source_revision_id' => $source->contentRevisionId,
            'source_content_hash' => $source->contentHash,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'translated_by_membership_id' => $translatorMembership->getKey(),
        ]);

        $second = $this->contexts()->run(
            $translatorContext,
            fn (): LocalizedVariantAppendResult => $commands->appendKannadaVariant(
                actor: $translator,
                curriculumVersionId: $draft['curriculum_version_id'],
                sourceRevisionId: $source->contentRevisionId,
                sourceContentHash: $source->contentHash,
                expectedSourceRevisionNumber: 1,
                expectedHeadVariantId: $first->localizedVariantId,
                expectedVariantRevisionNumber: 1,
                payload: ['instruction' => 'ದಯವಿಟ್ಟು ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.'],
                idempotencyKey: 'append-kannada-v2',
                translatorAttribution: 'synthetic-test-translator',
            ),
        );

        self::assertSame(2, $second->revisionNumber);
        self::assertSame(
            $first->localizedVariantId,
            $second->parentVariantId,
        );
        $this->assertRejected(
            fn (): LocalizedVariantAppendResult => $this->contexts()->run(
                $translatorContext,
                fn (): LocalizedVariantAppendResult => $commands->appendKannadaVariant(
                    actor: $translator,
                    curriculumVersionId: $draft['curriculum_version_id'],
                    sourceRevisionId: $source->contentRevisionId,
                    sourceContentHash: $source->contentHash,
                    expectedSourceRevisionNumber: 1,
                    expectedHeadVariantId: $first->localizedVariantId,
                    expectedVariantRevisionNumber: 1,
                    payload: ['instruction' => 'ಹಳೆಯ ತಿದ್ದುಪಡಿ.'],
                    idempotencyKey: 'stale-kannada-head',
                    translatorAttribution: 'synthetic-test-translator',
                ),
            ),
        );

        $successor = $this->contexts()->run(
            $authorContext,
            fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                actor: $author,
                curriculumVersionId: $draft['curriculum_version_id'],
                contentDocumentId: $draft['content_document_id'],
                expectedHeadRevisionId: $source->contentRevisionId,
                expectedRevisionNumber: 1,
                contentType: 'instruction',
                learnerVisible: true,
                localizationRequirement: 'kn_required',
                payload: ['instruction' => 'Please say your name.'],
                idempotencyKey: 'source-for-kannada-v2',
                authorAttribution: 'synthetic-test-author',
            ),
        );
        $this->assertRejected(
            fn (): LocalizedVariantAppendResult => $this->contexts()->run(
                $translatorContext,
                fn (): LocalizedVariantAppendResult => $commands->appendKannadaVariant(
                    actor: $translator,
                    curriculumVersionId: $draft['curriculum_version_id'],
                    sourceRevisionId: $source->contentRevisionId,
                    sourceContentHash: $source->contentHash,
                    expectedSourceRevisionNumber: 1,
                    expectedHeadVariantId: $second->localizedVariantId,
                    expectedVariantRevisionNumber: 2,
                    payload: ['instruction' => 'ಹಳೆಯ ಮೂಲ.'],
                    idempotencyKey: 'stale-kannada-source',
                    translatorAttribution: 'synthetic-test-translator',
                ),
            ),
        );
        $successorVariant = $this->contexts()->run(
            $translatorContext,
            fn (): LocalizedVariantAppendResult => $commands->appendKannadaVariant(
                actor: $translator,
                curriculumVersionId: $draft['curriculum_version_id'],
                sourceRevisionId: $successor->contentRevisionId,
                sourceContentHash: $successor->contentHash,
                expectedSourceRevisionNumber: 2,
                expectedHeadVariantId: null,
                expectedVariantRevisionNumber: 0,
                payload: ['instruction' => 'ದಯವಿಟ್ಟು ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.'],
                idempotencyKey: 'successor-kannada-v1',
                translatorAttribution: 'synthetic-test-translator',
            ),
        );

        self::assertSame(1, $successorVariant->revisionNumber);
        self::assertNull($successorVariant->parentVariantId);
        $this->assertDatabaseHas('outbox_events', [
            'organization_id' => $organization->getKey(),
            'event_type' => 'curriculum.localized_variant.appended',
            'aggregate_type' => 'localized_variant',
            'aggregate_id' => $first->localizedVariantId,
        ]);
        $this->assertAppendOnlyMutationRejected(
            'localized_variants',
            $first->localizedVariantId,
            ['translator_attribution' => 'forged-attribution'],
        );
    }

    public function test_authoring_is_permission_scoped_tenant_scoped_and_sealed_after_publication(): void
    {
        $organization = $this->createOrganization('Sealed Authoring Tenant');
        [$author, $authorMembership, $authorContext] =
            $this->actorWithPermission(
                $organization,
                'curriculum.author',
            );
        $unauthorizedUser = $this->createUser();
        [, $unauthorizedContext] = $this->createMembership(
            $organization,
            $unauthorizedUser,
            Membership::TYPE_TRAINER,
        );
        $otherOrganization = $this->createOrganization(
            'Other Authoring Tenant',
        );
        [$otherAuthor, , $otherContext] = $this->actorWithPermission(
            $otherOrganization,
            'curriculum.author',
        );
        $draft = $this->createDraft(
            (string) $organization->getKey(),
            (string) $authorMembership->getKey(),
        );
        $commands = $this->app->make(ContentAuthoringCommands::class);
        $request = fn (
            User $requestActor,
        ): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
            actor: $requestActor,
            curriculumVersionId: $draft['curriculum_version_id'],
            contentDocumentId: $draft['content_document_id'],
            expectedHeadRevisionId: null,
            expectedRevisionNumber: 0,
            contentType: 'instruction',
            learnerVisible: true,
            localizationRequirement: 'kn_required',
            payload: ['instruction' => 'Tenant-scoped fixture.'],
            idempotencyKey: 'tenant-scoped-source',
            authorAttribution: 'synthetic-test-author',
        );

        $this->assertRejected(
            fn (): ContentRevisionAppendResult => $this->contexts()->run(
                $unauthorizedContext,
                fn (): ContentRevisionAppendResult => $request($unauthorizedUser),
            ),
        );
        $this->assertRejected(
            fn (): ContentRevisionAppendResult => $this->contexts()->run(
                $otherContext,
                fn (): ContentRevisionAppendResult => $request($otherAuthor),
            ),
        );
        $source = $this->contexts()->run(
            $authorContext,
            fn (): ContentRevisionAppendResult => $request($author),
        );
        $publicationId = (string) Str::ulid();
        $manifest = ['fixture' => 'sealed-authoring-version'];
        $now = now();

        DB::table('curriculum_publications')->insert([
            'id' => $publicationId,
            'organization_id' => $organization->getKey(),
            'program_id' => $draft['program_id'],
            'curriculum_version_id' => $draft['curriculum_version_id'],
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => CanonicalJson::encode($manifest),
            'manifest_hash' => CanonicalJson::sha256($manifest),
            'created_by_membership_id' => $authorMembership->getKey(),
            'created_at' => $now,
        ]);

        $safeReplay = $this->contexts()->run(
            $authorContext,
            fn (): ContentRevisionAppendResult => $request($author),
        );

        self::assertTrue($safeReplay->replayed);
        self::assertSame(
            $source->contentRevisionId,
            $safeReplay->contentRevisionId,
        );
        $this->assertRejected(
            fn (): ContentRevisionAppendResult => $this->contexts()->run(
                $authorContext,
                fn (): ContentRevisionAppendResult => $commands->appendEnglishSourceRevision(
                    actor: $author,
                    curriculumVersionId: $draft['curriculum_version_id'],
                    contentDocumentId: $draft['content_document_id'],
                    expectedHeadRevisionId: $source->contentRevisionId,
                    expectedRevisionNumber: 1,
                    contentType: 'instruction',
                    learnerVisible: true,
                    localizationRequirement: 'kn_required',
                    payload: ['instruction' => 'Forbidden mutation.'],
                    idempotencyKey: 'sealed-source-v2',
                    authorAttribution: 'synthetic-test-author',
                ),
            ),
        );
        self::assertSame(
            1,
            DB::table('content_revisions')
                ->where('organization_id', $organization->getKey())
                ->where(
                    'curriculum_version_id',
                    $draft['curriculum_version_id'],
                )
                ->where(
                    'content_document_id',
                    $draft['content_document_id'],
                )
                ->count(),
        );
    }

    /**
     * @return array{User, Membership, OrganizationContext}
     */
    private function actorWithPermission(
        Organization $organization,
        string $permissionKey,
    ): array {
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermission($membership, $context, $permissionKey);

        return [$user, $membership, $context];
    }

    private function grantPermission(
        Membership $membership,
        OrganizationContext $context,
        string $permissionKey,
    ): void {
        $permission = Permission::query()
            ->where('key', $permissionKey)
            ->sole();
        $role = $this->createRole(
            $context,
            'authoring-'.strtolower((string) Str::ulid()),
        );
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organizationId(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $membership->organizationId(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array{
     *   program_id: string,
     *   curriculum_version_id: string,
     *   content_document_id: string
     * }
     */
    private function createDraft(
        string $organizationId,
        string $creatorMembershipId,
    ): array {
        $programId = (string) Str::ulid();
        $curriculumVersionId = (string) Str::ulid();
        $contentDocumentId = (string) Str::ulid();
        $now = now();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'authoring-'.strtolower((string) Str::ulid()),
            'internal_name' => 'Synthetic authoring fixture',
            'created_by_membership_id' => $creatorMembershipId,
            'created_by_attribution' => 'synthetic-test-author',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $curriculumVersionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => 'draft-1',
            'created_by_membership_id' => $creatorMembershipId,
            'created_by_attribution' => 'synthetic-test-author',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $contentDocumentId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'key' => 'synthetic-instruction',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'content_document_id' => $contentDocumentId,
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function assertAppendOnlyMutationRejected(
        string $table,
        string $id,
        array $values,
    ): void {
        try {
            DB::table($table)->where('id', $id)->update($values);
            self::fail('An immutable authoring record accepted mutation.');
        } catch (QueryException $exception) {
            self::assertSame('55000', (string) $exception->getCode());
            self::assertStringContainsString(
                'append-only',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(mixed $value): array
    {
        if (is_array($value) && ! array_is_list($value)) {
            return $value;
        }

        if (! is_string($value)) {
            self::fail('Expected a JSON object.');
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || array_is_list($decoded)) {
            self::fail('Expected a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('The content authoring operation should fail closed.');
        } catch (CurriculumOperationRejected) {
            return;
        }
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertIdempotencyConflict(callable $operation): void
    {
        try {
            $operation();
            self::fail('A conflicting idempotency replay should fail.');
        } catch (CurriculumIdempotencyConflict) {
            return;
        }
    }
}
