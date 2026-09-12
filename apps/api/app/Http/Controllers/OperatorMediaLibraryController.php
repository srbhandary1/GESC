<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\MediaRightsConfirmationCommands;
use App\Http\Problems\ProblemDetailsFactory;
use App\Http\Requests\Operator\MediaLibrary\MediaCommandRequest;
use App\Http\Requests\Operator\MediaLibrary\MediaReadRequest;
use App\Learning\Media\VideoRangeNotSatisfiable;
use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Operator\MediaLibrary\MediaLibraryAuthorization;
use App\Operator\MediaLibrary\MediaLibraryCommandResult;
use App\Operator\MediaLibrary\MediaLibraryQuery;
use App\Operator\MediaLibrary\MediaLifecycleCommands;
use App\Operator\MediaLibrary\MediaMetadataCommands;
use App\Operator\MediaLibrary\MediaPreviewResponder;
use App\Operator\MediaLibrary\MediaUploadCommands;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperatorMediaLibraryController extends Controller
{
    public function __construct(
        private readonly MediaLibraryQuery $queries,
        private readonly MediaUploadCommands $uploads,
        private readonly MediaMetadataCommands $metadata,
        private readonly MediaLifecycleCommands $lifecycles,
        private readonly MediaRightsConfirmationCommands $rights,
        private readonly MediaPreviewResponder $previews,
        private readonly MediaLibraryAuthorization $authorization,
        private readonly ProblemDetailsFactory $problems,
    ) {}

    public function manifest(Request $request): JsonResponse
    {
        return $this->read(
            fn (User $actor): array => $this->queries->manifest($actor),
            $request,
        );
    }

    public function assets(MediaReadRequest $request): JsonResponse
    {
        return $this->read(function (User $actor) use ($request): array {
            $cursor = $request->validated('cursor');
            $kind = $request->validated('kind');
            $state = $request->validated('state');

            return $this->queries->assets(
                $actor,
                (int) ($request->validated('limit') ?? 25),
                is_string($cursor) ? $cursor : null,
                is_string($kind) ? $kind : null,
                is_string($state) ? $state : null,
            );
        }, $request);
    }

    public function asset(Request $request, string $asset): JsonResponse
    {
        return $this->read(
            fn (User $actor): array => $this->queries->asset($actor, $asset),
            $request,
        );
    }

    public function assetPreview(
        Request $request,
        string $asset,
    ): JsonResponse|StreamedResponse {
        try {
            return $this->previews->asset(
                $this->actor($request),
                $asset,
                $request,
            );
        } catch (VideoRangeNotSatisfiable $exception) {
            return $this->problems->rangeNotSatisfiable(
                $request,
                $exception->totalBytes,
                $exception->etag,
            );
        }
    }

    public function uploads(MediaReadRequest $request): JsonResponse
    {
        return $this->read(function (User $actor) use ($request): array {
            $cursor = $request->validated('cursor');
            $state = $request->validated('state');

            return $this->queries->uploads(
                $actor,
                (int) ($request->validated('limit') ?? 25),
                is_string($cursor) ? $cursor : null,
                is_string($state) ? $state : null,
            );
        }, $request);
    }

    public function upload(Request $request, string $upload): JsonResponse
    {
        return $this->read(
            fn (User $actor): array => $this->queries->upload($actor, $upload),
            $request,
        );
    }

    public function uploadPreview(
        Request $request,
        string $upload,
    ): JsonResponse|StreamedResponse {
        try {
            return $this->previews->upload(
                $this->actor($request),
                $upload,
                $request,
            );
        } catch (VideoRangeNotSatisfiable $exception) {
            return $this->problems->rangeNotSatisfiable(
                $request,
                $exception->totalBytes,
                $exception->etag,
            );
        }
    }

    public function startUpload(MediaCommandRequest $request): JsonResponse
    {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->uploads->start(
            actor: $this->actor($request),
            assetKind: (string) $request->validated('asset_kind'),
            safeLabel: (string) $request->validated('safe_label'),
            totalBytes: (int) $request->validated('total_bytes'),
            wholeObjectHash: (string) $request->validated('whole_object_hash'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function storePart(
        MediaCommandRequest $request,
        string $upload,
    ): JsonResponse {
        $bytes = $request->getContent();

        if ($bytes === '') {
            throw ValidationException::withMessages([
                'body' => ['The binary media part is required.'],
            ]);
        }

        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->uploads->part(
            actor: $this->actor($request),
            uploadId: $upload,
            partNumber: (int) $request->validated('part_number'),
            expectedUploadVersion: (int) $request->validated(
                'expected_upload_version',
            ),
            partHash: (string) $request->validated('part_hash'),
            bytes: $bytes,
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function finalizeUpload(
        MediaCommandRequest $request,
        string $upload,
    ): JsonResponse {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->uploads->finalize(
            actor: $this->actor($request),
            uploadId: $upload,
            expectedUploadVersion: (int) $request->validated(
                'expected_upload_version',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function acceptUpload(
        MediaCommandRequest $request,
        string $upload,
    ): JsonResponse {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->uploads->accept(
            actor: $this->actor($request),
            uploadId: $upload,
            expectedUploadVersion: (int) $request->validated(
                'expected_upload_version',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function rejectUpload(
        MediaCommandRequest $request,
        string $upload,
    ): JsonResponse {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->uploads->reject(
            actor: $this->actor($request),
            uploadId: $upload,
            expectedUploadVersion: (int) $request->validated(
                'expected_upload_version',
            ),
            reasonKey: (string) $request->validated('reason_key'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function adoptAsset(
        MediaCommandRequest $request,
        string $asset,
    ): JsonResponse {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->uploads->adopt(
            actor: $this->actor($request),
            assetId: $asset,
            selectedAssetHash: (string) $request->validated(
                'selected_asset_hash',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function appendMetadata(
        MediaCommandRequest $request,
        string $asset,
    ): JsonResponse {
        $expectedHead = $request->validated('expected_head_revision_id');
        $textAlternative = $request->validated('text_alternative');

        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->metadata->appendEnglish(
            actor: $this->actor($request),
            assetId: $asset,
            expectedHeadRevisionId: is_string($expectedHead)
                ? $expectedHead
                : null,
            expectedMetadataVersion: (int) $request->validated(
                'expected_metadata_version',
            ),
            operatorLabel: (string) $request->validated('operator_label'),
            transcript: (string) $request->validated('transcript'),
            textAlternative: is_string($textAlternative)
                ? $textAlternative
                : null,
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function appendKannada(
        MediaCommandRequest $request,
        string $revision,
    ): JsonResponse {
        $expectedVariant = $request->validated('expected_head_variant_id');
        $textAlternative = $request->validated('text_alternative');

        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->metadata->appendKannada(
            actor: $this->actor($request),
            metadataRevisionId: $revision,
            sourceContentHash: (string) $request->validated(
                'source_content_hash',
            ),
            expectedHeadVariantId: is_string($expectedVariant)
                ? $expectedVariant
                : null,
            expectedVariantVersion: (int) $request->validated(
                'expected_variant_version',
            ),
            transcript: (string) $request->validated('transcript'),
            textAlternative: is_string($textAlternative)
                ? $textAlternative
                : null,
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function reviewMetadata(MediaCommandRequest $request): JsonResponse
    {
        $variantId = $request->validated('metadata_variant_id');
        $variantHash = $request->validated('variant_content_hash');
        $rationale = $request->validated('rationale_key');

        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->metadata->review(
            actor: $this->actor($request),
            programId: (string) $request->validated('program_id'),
            metadataRevisionId: (string) $request->validated(
                'metadata_revision_id',
            ),
            sourceContentHash: (string) $request->validated(
                'source_content_hash',
            ),
            reviewKind: (string) $request->validated('review_kind'),
            metadataVariantId: is_string($variantId) ? $variantId : null,
            variantContentHash: is_string($variantHash) ? $variantHash : null,
            decision: (string) $request->validated('decision'),
            rationaleKey: is_string($rationale) ? $rationale : null,
            authorityAppointmentId: (string) $request->validated(
                'authority_appointment_id',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function rightsDecision(MediaCommandRequest $request): JsonResponse
    {
        try {
            $action = (string) $request->validated('action');
            $actor = $this->actor($request);
            $programId = (string) $request->validated('program_id');
            $parent = $request->validated('parent_revision_id');
            $parentRevisionId = is_string($parent) ? $parent : null;
            $idempotencyKey = (string) $request->validated('idempotency_key');
            $appointmentId = (string) $request->validated(
                'authority_appointment_id',
            );
            $evidenceHash = (string) $request->validated('evidence_hash');
            $licenseReference = (string) $request->validated(
                'license_or_contract_reference',
            );

            if ($action === 'confirm') {
                $assetId = (string) $request->validated('media_asset_id');
                $selectedHash = (string) $request->validated(
                    'selected_asset_hash',
                );
                $this->assertManagedAsset($actor, $assetId, $selectedHash);
                $expires = $request->validated('expires_at');
                $rightsRevisionId = $this->rights->confirm(
                    actor: $actor,
                    programId: $programId,
                    mediaAssetId: $assetId,
                    selectedAssetHash: $selectedHash,
                    parentRevisionId: $parentRevisionId,
                    evidenceHash: $evidenceHash,
                    rightsHolderReference: (string) $request->validated(
                        'rights_holder_reference',
                    ),
                    provenanceReference: (string) $request->validated(
                        'provenance_reference',
                    ),
                    rightsBasisKey: (string) $request->validated(
                        'rights_basis_key',
                    ),
                    licenseOrContractReference: $licenseReference,
                    permittedReleaseChannels: $this->stringList(
                        $request->validated('permitted_release_channels'),
                    ),
                    permittedTerritories: $this->stringList(
                        $request->validated('permitted_territories'),
                    ),
                    learnerDeliveryAllowed: (bool) $request->validated(
                        'learner_delivery_allowed',
                    ),
                    sharingAllowed: (bool) $request->validated(
                        'sharing_allowed',
                    ),
                    derivativeUseAllowed: (bool) $request->validated(
                        'derivative_use_allowed',
                    ),
                    effectiveAt: CarbonImmutable::parse(
                        (string) $request->validated('effective_at'),
                    ),
                    expiresAt: is_string($expires)
                        ? CarbonImmutable::parse($expires)
                        : null,
                    retentionPolicyKey: (string) $request->validated(
                        'retention_policy_key',
                    ),
                    deletionConstraintKey: (string) $request->validated(
                        'deletion_constraint_key',
                    ),
                    authorityAppointmentId: $appointmentId,
                    idempotencyKey: $idempotencyKey,
                );
            } else {
                if ($parentRevisionId === null) {
                    throw ValidationException::withMessages([
                        'parent_revision_id' => [
                            'A parent rights revision is required.',
                        ],
                    ]);
                }

                $rightsRevisionId = $action === 'reject'
                    ? $this->rights->reject(
                        actor: $actor,
                        programId: $programId,
                        parentRevisionId: $parentRevisionId,
                        evidenceHash: $evidenceHash,
                        licenseOrContractReference: $licenseReference,
                        authorityAppointmentId: $appointmentId,
                        idempotencyKey: $idempotencyKey,
                    )
                    : $this->rights->withdraw(
                        actor: $actor,
                        programId: $programId,
                        parentRevisionId: $parentRevisionId,
                        evidenceHash: $evidenceHash,
                        licenseOrContractReference: $licenseReference,
                        authorityAppointmentId: $appointmentId,
                        idempotencyKey: $idempotencyKey,
                    );
            }

            return response()->json([
                'media_rights_revision_id' => $rightsRevisionId,
                'action' => $action,
            ], Response::HTTP_CREATED)->header(
                'Cache-Control',
                'private, no-store',
            );
        } catch (CurriculumIdempotencyConflict $exception) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::IDEMPOTENCY,
                $exception,
            );
        } catch (CurriculumOperationRejected $exception) {
            throw new MediaLibraryRejected($exception);
        }
    }

    public function attach(
        MediaCommandRequest $request,
        string $asset,
    ): JsonResponse {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->lifecycles->attach(
            actor: $this->actor($request),
            assetId: $asset,
            programId: (string) $request->validated('program_id'),
            curriculumVersionId: (string) $request->validated(
                'curriculum_version_id',
            ),
            sourceRevisionId: (string) $request->validated(
                'source_revision_id',
            ),
            usageKey: (string) $request->validated('usage_key'),
            expectedWorkspaceVersion: (int) $request->validated(
                'expected_workspace_version',
            ),
            expectedLifecycleVersion: (int) $request->validated(
                'expected_lifecycle_version',
            ),
            metadataRevisionId: (string) $request->validated(
                'metadata_revision_id',
            ),
            metadataContentHash: (string) $request->validated(
                'metadata_content_hash',
            ),
            englishReviewId: (string) $request->validated(
                'english_review_id',
            ),
            metadataVariantId: (string) $request->validated(
                'metadata_variant_id',
            ),
            variantContentHash: (string) $request->validated(
                'variant_content_hash',
            ),
            kannadaReviewId: (string) $request->validated(
                'kannada_review_id',
            ),
            mediaRightsRevisionId: (string) $request->validated(
                'media_rights_revision_id',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function replace(
        MediaCommandRequest $request,
        string $asset,
    ): JsonResponse {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->lifecycles->replace(
            actor: $this->actor($request),
            predecessorAssetId: $asset,
            successorAssetId: (string) $request->validated(
                'successor_media_asset_id',
            ),
            expectedPredecessorLifecycleVersion: (int) $request->validated(
                'expected_predecessor_lifecycle_version',
            ),
            expectedSuccessorLifecycleVersion: (int) $request->validated(
                'expected_successor_lifecycle_version',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function retire(
        MediaCommandRequest $request,
        string $asset,
    ): JsonResponse {
        return $this->runCommand(fn (): MediaLibraryCommandResult => $this->lifecycles->retire(
            actor: $this->actor($request),
            assetId: $asset,
            expectedLifecycleVersion: (int) $request->validated(
                'expected_lifecycle_version',
            ),
            reasonKey: (string) $request->validated('reason_key'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    /** @param callable(User): array<string, mixed> $callback */
    private function read(callable $callback, Request $request): JsonResponse
    {
        try {
            $payload = $callback($this->actor($request));
        } catch (InvalidOperatorCursor) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid or expired.'],
            ]);
        }

        return response()->json($payload)->header(
            'Cache-Control',
            'private, no-store',
        );
    }

    /** @param callable(): MediaLibraryCommandResult $callback */
    private function runCommand(callable $callback): JsonResponse
    {
        try {
            $result = $callback();
        } catch (QueryException $exception) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::CONCURRENT_CHANGE,
                $exception,
            );
        }

        return response()->json([
            'result_type' => $result->resultType,
            'result_id' => $result->resultId,
            'replayed' => $result->replayed,
            ...$result->data,
        ], $result->replayed ? Response::HTTP_OK : Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    private function assertManagedAsset(
        User $actor,
        string $assetId,
        string $assetHash,
    ): void {
        $this->authorization->assert($actor, 'media.rights.approve');
        $organizationId = $this->authorization->context($actor)->organizationId;
        $asset = DB::table('media_assets')
            ->join('media_asset_lifecycles', function ($join): void {
                $join
                    ->on(
                        'media_asset_lifecycles.organization_id',
                        '=',
                        'media_assets.organization_id',
                    )
                    ->on(
                        'media_asset_lifecycles.media_asset_id',
                        '=',
                        'media_assets.id',
                    );
            })
            ->where('media_assets.organization_id', $organizationId)
            ->where('media_assets.id', $assetId)
            ->where('media_assets.asset_hash', $assetHash)
            ->where('media_asset_lifecycles.state', 'active')
            ->first(['media_asset_lifecycles.selected_scan_id']);

        if (! $asset instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        $scanId = $asset->selected_scan_id ?? null;

        if (
            ! is_string($scanId)
            || ! DB::table('media_security_scans')
                ->where('organization_id', $organizationId)
                ->where('id', $scanId)
                ->where('object_hash', $assetHash)
                ->where('inspection_outcome', 'passed')
                ->where('malware_outcome', 'clean')
                ->exists()
        ) {
            throw new MediaLibraryRejected;
        }
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages([
                'body' => ['The value must be a list of strings.'],
            ]);
        }

        $result = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw ValidationException::withMessages([
                    'body' => ['The value must be a list of strings.'],
                ]);
            }

            $result[] = $item;
        }

        return $result;
    }

    private function actor(Request $request): User
    {
        return AuthenticatedPrincipal::fromRequest($request)->user;
    }
}
