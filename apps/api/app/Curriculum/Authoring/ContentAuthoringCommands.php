<?php

declare(strict_types=1);

namespace App\Curriculum\Authoring;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\GovernanceCommandIdempotency;
use App\Curriculum\Governance\GovernanceInput;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class ContentAuthoringCommands
{
    private const string APPEND_SOURCE_OPERATION =
        'curriculum.content_revision.append';

    private const string APPEND_KANNADA_OPERATION =
        'curriculum.localized_variant.append';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private GovernanceCommandIdempotency $idempotency,
        private ContentClassificationPolicy $classification,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function appendEnglishSourceRevision(
        User $actor,
        string $curriculumVersionId,
        string $contentDocumentId,
        ?string $expectedHeadRevisionId,
        int $expectedRevisionNumber,
        string $contentType,
        bool $learnerVisible,
        string $localizationRequirement,
        array $payload,
        string $idempotencyKey,
        string $authorAttribution,
    ): ContentRevisionAppendResult {
        GovernanceInput::ulid($curriculumVersionId);
        GovernanceInput::ulid($contentDocumentId);
        $this->expectedHead($expectedHeadRevisionId, $expectedRevisionNumber);
        GovernanceInput::idempotencyKey($idempotencyKey);
        $authorAttribution = GovernanceInput::text($authorAttribution, 120);
        $payload = $this->payload($payload);
        $classification = $this->classification->classify(
            $contentType,
            $learnerVisible,
            $localizationRequirement,
        );
        $contentHash = CanonicalJson::sha256($payload);

        try {
            return DB::transaction(function () use (
                $actor,
                $curriculumVersionId,
                $contentDocumentId,
                $expectedHeadRevisionId,
                $expectedRevisionNumber,
                $payload,
                $idempotencyKey,
                $authorAttribution,
                $classification,
                $contentHash,
            ): ContentRevisionAppendResult {
                $actorMembershipId = $this->assertPermission(
                    $actor,
                    'curriculum.author',
                );
                $organizationId = $this->contexts->current()->organizationId;
                $this->lockVersion($organizationId, $curriculumVersionId);
                $this->lockDocument(
                    $organizationId,
                    $curriculumVersionId,
                    $contentDocumentId,
                );
                $fingerprint = $this->idempotency->fingerprint([
                    'author_attribution' => $authorAttribution,
                    'content_document_id' => $contentDocumentId,
                    'content_type' => $classification->contentType,
                    'curriculum_version_id' => $curriculumVersionId,
                    'expected_head_revision_id' => $expectedHeadRevisionId,
                    'expected_revision_number' => $expectedRevisionNumber,
                    'learner_visible' => $classification->learnerVisible,
                    'localization_requirement' => $classification
                        ->localizationRequirement,
                    'operation' => self::APPEND_SOURCE_OPERATION,
                    'payload' => $payload,
                    'pedagogically_authoritative' => $classification
                        ->pedagogicallyAuthoritative,
                ]);
                $replayedRevisionId = $this->idempotency->replay(
                    actor: $actor,
                    operation: self::APPEND_SOURCE_OPERATION,
                    aggregateType: 'content_document',
                    aggregateId: $contentDocumentId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                );

                if ($replayedRevisionId !== null) {
                    return $this->sourceResult(
                        organizationId: $organizationId,
                        curriculumVersionId: $curriculumVersionId,
                        contentDocumentId: $contentDocumentId,
                        contentRevisionId: $replayedRevisionId,
                        replayed: true,
                    );
                }

                $this->assertVersionRemainsDraft(
                    $organizationId,
                    $curriculumVersionId,
                );
                $head = DB::table('content_revision_heads')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('content_document_id', $contentDocumentId)
                    ->lockForUpdate()
                    ->first(['content_revision_id']);
                $currentRevisionId = $head instanceof stdClass
                    ? $this->string($head, 'content_revision_id')
                    : null;
                $currentRevisionNumber = $currentRevisionId === null
                    ? 0
                    : $this->sourceRevisionNumber(
                        $organizationId,
                        $curriculumVersionId,
                        $contentDocumentId,
                        $currentRevisionId,
                    );

                if (
                    $currentRevisionNumber !== $expectedRevisionNumber
                    || ! $this->sameNullableIdentifier(
                        $currentRevisionId,
                        $expectedHeadRevisionId,
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                $revisionId = (string) Str::ulid();
                $revisionNumber = $currentRevisionNumber + 1;
                $now = Date::now();

                DB::table('content_revisions')->insert([
                    'id' => $revisionId,
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'content_document_id' => $contentDocumentId,
                    'parent_revision_id' => $currentRevisionId,
                    'revision_number' => $revisionNumber,
                    'source_locale' => 'en-IN',
                    'content_type' => $classification->contentType,
                    'learner_visible' => $classification->learnerVisible,
                    'localization_requirement' => $classification
                        ->localizationRequirement,
                    'pedagogically_authoritative' => $classification
                        ->pedagogicallyAuthoritative,
                    'payload' => CanonicalJson::encode($payload),
                    'content_hash' => $contentHash,
                    'authored_by_membership_id' => $actorMembershipId,
                    'author_attribution' => $authorAttribution,
                    'authored_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($head instanceof stdClass) {
                    $updated = DB::table('content_revision_heads')
                        ->where('organization_id', $organizationId)
                        ->where('curriculum_version_id', $curriculumVersionId)
                        ->where('content_document_id', $contentDocumentId)
                        ->where('content_revision_id', $currentRevisionId)
                        ->update([
                            'content_revision_id' => $revisionId,
                            'updated_at' => $now,
                        ]);

                    if ($updated !== 1) {
                        throw new CurriculumOperationRejected;
                    }
                } else {
                    DB::table('content_revision_heads')->insert([
                        'organization_id' => $organizationId,
                        'curriculum_version_id' => $curriculumVersionId,
                        'content_document_id' => $contentDocumentId,
                        'content_revision_id' => $revisionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->idempotency->record(
                    actor: $actor,
                    operation: self::APPEND_SOURCE_OPERATION,
                    aggregateType: 'content_document',
                    aggregateId: $contentDocumentId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                    resultType: 'content_revision',
                    resultId: $revisionId,
                    completedAt: $now,
                );
                $this->audit->recordTenant(
                    'curriculum.content_revision.appended',
                    'content_revision',
                    $revisionId,
                );
                $this->outbox->recordTenant(
                    'curriculum.content_revision.appended',
                    'content_revision',
                    $revisionId,
                    [
                        'curriculum_version_id' => $curriculumVersionId,
                        'membership_id' => $actorMembershipId,
                        'organization_id' => $organizationId,
                        'source_revision_id' => $revisionId,
                    ],
                );

                return new ContentRevisionAppendResult(
                    organizationId: $organizationId,
                    curriculumVersionId: $curriculumVersionId,
                    contentDocumentId: $contentDocumentId,
                    contentRevisionId: $revisionId,
                    parentRevisionId: $currentRevisionId,
                    revisionNumber: $revisionNumber,
                    contentHash: $contentHash,
                    replayed: false,
                );
            }, 3);
        } catch (QueryException $exception) {
            $this->rejectExpectedDatabaseConflict($exception);

            throw $exception;
        }
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function appendKannadaVariant(
        User $actor,
        string $curriculumVersionId,
        string $sourceRevisionId,
        string $sourceContentHash,
        int $expectedSourceRevisionNumber,
        ?string $expectedHeadVariantId,
        int $expectedVariantRevisionNumber,
        array $payload,
        string $idempotencyKey,
        string $translatorAttribution,
    ): LocalizedVariantAppendResult {
        GovernanceInput::ulid($curriculumVersionId);
        GovernanceInput::ulid($sourceRevisionId);
        GovernanceInput::sha256($sourceContentHash);
        $this->positiveRevisionNumber($expectedSourceRevisionNumber);
        $this->expectedHead(
            $expectedHeadVariantId,
            $expectedVariantRevisionNumber,
        );
        GovernanceInput::idempotencyKey($idempotencyKey);
        $translatorAttribution = GovernanceInput::text(
            $translatorAttribution,
            120,
        );
        $payload = $this->payload($payload);
        $contentHash = CanonicalJson::sha256($payload);

        try {
            return DB::transaction(function () use (
                $actor,
                $curriculumVersionId,
                $sourceRevisionId,
                $sourceContentHash,
                $expectedSourceRevisionNumber,
                $expectedHeadVariantId,
                $expectedVariantRevisionNumber,
                $payload,
                $idempotencyKey,
                $translatorAttribution,
                $contentHash,
            ): LocalizedVariantAppendResult {
                $translatorMembershipId = $this->assertPermission(
                    $actor,
                    'curriculum.translate.kn',
                );
                $organizationId = $this->contexts->current()->organizationId;
                $this->lockVersion($organizationId, $curriculumVersionId);
                $source = DB::table('content_revisions')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('id', $sourceRevisionId)
                    ->where('content_hash', $sourceContentHash)
                    ->lockForUpdate()
                    ->first([
                        'content_document_id',
                        'revision_number',
                    ]);

                if (! $source instanceof stdClass) {
                    throw new CurriculumOperationRejected;
                }

                $fingerprint = $this->idempotency->fingerprint([
                    'curriculum_version_id' => $curriculumVersionId,
                    'expected_head_variant_id' => $expectedHeadVariantId,
                    'expected_source_revision_number' => $expectedSourceRevisionNumber,
                    'expected_variant_revision_number' => $expectedVariantRevisionNumber,
                    'locale' => 'kn-IN',
                    'operation' => self::APPEND_KANNADA_OPERATION,
                    'payload' => $payload,
                    'source_content_hash' => $sourceContentHash,
                    'source_revision_id' => $sourceRevisionId,
                    'translator_attribution' => $translatorAttribution,
                ]);
                $replayedVariantId = $this->idempotency->replay(
                    actor: $actor,
                    operation: self::APPEND_KANNADA_OPERATION,
                    aggregateType: 'source_revision',
                    aggregateId: $sourceRevisionId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                );

                if ($replayedVariantId !== null) {
                    return $this->variantResult(
                        organizationId: $organizationId,
                        curriculumVersionId: $curriculumVersionId,
                        sourceRevisionId: $sourceRevisionId,
                        localizedVariantId: $replayedVariantId,
                        replayed: true,
                    );
                }

                $this->assertVersionRemainsDraft(
                    $organizationId,
                    $curriculumVersionId,
                );
                $sourceRevisionNumber = $this->integer(
                    $source,
                    'revision_number',
                );
                $contentDocumentId = $this->string(
                    $source,
                    'content_document_id',
                );
                $sourceHead = DB::table('content_revision_heads')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('content_document_id', $contentDocumentId)
                    ->lockForUpdate()
                    ->first(['content_revision_id']);

                if (
                    $sourceRevisionNumber !== $expectedSourceRevisionNumber
                    || ! $sourceHead instanceof stdClass
                    || ! hash_equals(
                        $sourceRevisionId,
                        $this->string(
                            $sourceHead,
                            'content_revision_id',
                        ),
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                $head = DB::table('localized_variants')
                    ->where('organization_id', $organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->where('source_revision_id', $sourceRevisionId)
                    ->where('source_content_hash', $sourceContentHash)
                    ->where('locale', 'kn-IN')
                    ->orderByDesc('revision_number')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first(['id', 'revision_number']);
                $currentVariantId = $head instanceof stdClass
                    ? $this->string($head, 'id')
                    : null;
                $currentVariantRevisionNumber = $head instanceof stdClass
                    ? $this->integer($head, 'revision_number')
                    : 0;

                if (
                    $currentVariantRevisionNumber
                        !== $expectedVariantRevisionNumber
                    || ! $this->sameNullableIdentifier(
                        $currentVariantId,
                        $expectedHeadVariantId,
                    )
                ) {
                    throw new CurriculumOperationRejected;
                }

                $variantId = (string) Str::ulid();
                $variantRevisionNumber =
                    $currentVariantRevisionNumber + 1;
                $now = Date::now();

                DB::table('localized_variants')->insert([
                    'id' => $variantId,
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'source_revision_id' => $sourceRevisionId,
                    'parent_variant_id' => $currentVariantId,
                    'locale' => 'kn-IN',
                    'revision_number' => $variantRevisionNumber,
                    'source_content_hash' => $sourceContentHash,
                    'payload' => CanonicalJson::encode($payload),
                    'content_hash' => $contentHash,
                    'translated_by_membership_id' => $translatorMembershipId,
                    'translator_attribution' => $translatorAttribution,
                    'translated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->idempotency->record(
                    actor: $actor,
                    operation: self::APPEND_KANNADA_OPERATION,
                    aggregateType: 'source_revision',
                    aggregateId: $sourceRevisionId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                    resultType: 'localized_variant',
                    resultId: $variantId,
                    completedAt: $now,
                );
                $this->audit->recordTenant(
                    'curriculum.localized_variant.appended',
                    'localized_variant',
                    $variantId,
                );
                $this->outbox->recordTenant(
                    'curriculum.localized_variant.appended',
                    'localized_variant',
                    $variantId,
                    [
                        'curriculum_version_id' => $curriculumVersionId,
                        'localized_variant_id' => $variantId,
                        'membership_id' => $translatorMembershipId,
                        'organization_id' => $organizationId,
                        'source_revision_id' => $sourceRevisionId,
                    ],
                );

                return new LocalizedVariantAppendResult(
                    organizationId: $organizationId,
                    curriculumVersionId: $curriculumVersionId,
                    sourceRevisionId: $sourceRevisionId,
                    localizedVariantId: $variantId,
                    parentVariantId: $currentVariantId,
                    revisionNumber: $variantRevisionNumber,
                    sourceContentHash: $sourceContentHash,
                    contentHash: $contentHash,
                    replayed: false,
                );
            }, 3);
        } catch (QueryException $exception) {
            $this->rejectExpectedDatabaseConflict($exception);

            throw $exception;
        }
    }

    private function assertPermission(User $actor, string $permission): string
    {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || ! hash_equals(
                $context->actorUserId,
                (string) $actor->getKey(),
            )
            || ! $this->authorization->allows($actor, $permission)
        ) {
            throw new CurriculumOperationRejected;
        }

        return $context->membershipId;
    }

    private function lockVersion(
        string $organizationId,
        string $curriculumVersionId,
    ): void {
        $version = DB::table('curriculum_versions')
            ->where('organization_id', $organizationId)
            ->where('id', $curriculumVersionId)
            ->lockForUpdate()
            ->first(['id']);

        if (! $version instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }
    }

    private function lockDocument(
        string $organizationId,
        string $curriculumVersionId,
        string $contentDocumentId,
    ): void {
        $document = DB::table('content_documents')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('id', $contentDocumentId)
            ->lockForUpdate()
            ->first(['id']);

        if (! $document instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }
    }

    private function assertVersionRemainsDraft(
        string $organizationId,
        string $curriculumVersionId,
    ): void {
        if (
            DB::table('curriculum_publications')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function sourceRevisionNumber(
        string $organizationId,
        string $curriculumVersionId,
        string $contentDocumentId,
        string $contentRevisionId,
    ): int {
        $revision = DB::table('content_revisions')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('content_document_id', $contentDocumentId)
            ->where('id', $contentRevisionId)
            ->first(['revision_number']);

        if (! $revision instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return $this->integer($revision, 'revision_number');
    }

    private function sourceResult(
        string $organizationId,
        string $curriculumVersionId,
        string $contentDocumentId,
        string $contentRevisionId,
        bool $replayed,
    ): ContentRevisionAppendResult {
        $revision = DB::table('content_revisions')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('content_document_id', $contentDocumentId)
            ->where('id', $contentRevisionId)
            ->first([
                'parent_revision_id',
                'revision_number',
                'content_hash',
            ]);

        if (! $revision instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return new ContentRevisionAppendResult(
            organizationId: $organizationId,
            curriculumVersionId: $curriculumVersionId,
            contentDocumentId: $contentDocumentId,
            contentRevisionId: $contentRevisionId,
            parentRevisionId: $this->nullableString(
                $revision,
                'parent_revision_id',
            ),
            revisionNumber: $this->integer($revision, 'revision_number'),
            contentHash: $this->sha256($revision, 'content_hash'),
            replayed: $replayed,
        );
    }

    private function variantResult(
        string $organizationId,
        string $curriculumVersionId,
        string $sourceRevisionId,
        string $localizedVariantId,
        bool $replayed,
    ): LocalizedVariantAppendResult {
        $variant = DB::table('localized_variants')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('source_revision_id', $sourceRevisionId)
            ->where('id', $localizedVariantId)
            ->where('locale', 'kn-IN')
            ->first([
                'parent_variant_id',
                'revision_number',
                'source_content_hash',
                'content_hash',
            ]);

        if (! $variant instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return new LocalizedVariantAppendResult(
            organizationId: $organizationId,
            curriculumVersionId: $curriculumVersionId,
            sourceRevisionId: $sourceRevisionId,
            localizedVariantId: $localizedVariantId,
            parentVariantId: $this->nullableString(
                $variant,
                'parent_variant_id',
            ),
            revisionNumber: $this->integer($variant, 'revision_number'),
            sourceContentHash: $this->sha256(
                $variant,
                'source_content_hash',
            ),
            contentHash: $this->sha256($variant, 'content_hash'),
            replayed: $replayed,
        );
    }

    private function expectedHead(
        ?string $expectedHeadId,
        int $expectedRevisionNumber,
    ): void {
        if ($expectedHeadId !== null) {
            GovernanceInput::ulid($expectedHeadId);
        }

        if (
            $expectedRevisionNumber < 0
            || ($expectedHeadId === null) !== ($expectedRevisionNumber === 0)
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function positiveRevisionNumber(int $revisionNumber): void
    {
        if ($revisionNumber < 1) {
            throw new CurriculumOperationRejected;
        }
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payload(array $payload): array
    {
        if ($payload === []) {
            throw new CurriculumOperationRejected;
        }

        $object = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                throw new CurriculumOperationRejected;
            }

            $object[$key] = $value;
        }

        CanonicalJson::encode($object);

        return $object;
    }

    private function sameNullableIdentifier(
        ?string $left,
        ?string $right,
    ): bool {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return hash_equals($left, $right);
    }

    private function rejectExpectedDatabaseConflict(
        QueryException $exception,
    ): void {
        if (
            in_array(
                (string) $exception->getCode(),
                ['23505', '55000'],
                true,
            )
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function sha256(stdClass $row, string $field): string
    {
        $value = $this->string($row, $field);

        return GovernanceInput::sha256($value);
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new CurriculumOperationRejected;
    }
}
