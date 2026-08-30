<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Curriculum\Governance\MediaRightsConfirmationCommands;
use App\Models\PasswordCredential;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class App0062LessonAudioIngestor
{
    public function __construct(
        private readonly MediaUploadCommands $uploads,
        private readonly MediaRightsConfirmationCommands $rights,
        private readonly OrganizationContextManager $contexts,
    ) {}

    /**
     * @return array{
     *   skipped_existing: int,
     *   accepted: int,
     *   rights_confirmed: int,
     *   failures: list<array{sha256: string, safe_error_code: string}>
     * }
     */
    public function ingest(
        string $actorUsername,
        string $actorPassword,
        string $inventoryPath,
        string $stagingManifestPath,
        string $programId,
        string $appointmentId,
        string $approvalReference,
    ): array {
        if ($approvalReference !== 'APP-0062') {
            throw new RuntimeException('approval_reference_invalid');
        }

        $actor = User::query()->where('username', $actorUsername)->first();
        if ($actor === null) {
            throw new RuntimeException('actor_missing');
        }

        $credential = PasswordCredential::query()->whereKey($actor->getKey())->first();
        if (
            ! $credential instanceof PasswordCredential
            || ! password_verify($actorPassword, (string) $credential->password_hash)
        ) {
            throw new RuntimeException('actor_password_invalid');
        }

        $membership = DB::table('memberships')
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->where('membership_type', 'organization_admin')
            ->first();
        if ($membership === null) {
            throw new RuntimeException('membership_missing');
        }

        $context = new OrganizationContext(
            organizationId: (string) $membership->organization_id,
            actorUserId: (string) $actor->getKey(),
            membershipId: (string) $membership->id,
            source: OrganizationContextSource::Membership,
        );

        /** @var array{parts: list<array<string, mixed>>} $inventory */
        $inventory = json_decode(
            (string) file_get_contents($inventoryPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        /** @var list<array<string, mixed>> $staging */
        $staging = json_decode(
            (string) file_get_contents($stagingManifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $pathByHash = [];
        foreach ($staging as $row) {
            $hash = (string) ($row['sha256'] ?? '');
            $name = (string) ($row['local_name'] ?? '');
            if ($hash !== '' && $name !== '') {
                $pathByHash[$hash] = '/tmp/legacy-audio-bytes/'.$name;
            }
        }

        $existing = DB::table('media_assets')
            ->where('organization_id', $context->organizationId)
            ->where('asset_kind', 'lesson_audio')
            ->pluck('id', 'asset_hash')
            ->all();

        $skipped = 0;
        $accepted = 0;
        $rightsConfirmed = 0;
        $failures = [];

        return $this->contexts->run($context, function () use (
            $actor,
            $inventory,
            $pathByHash,
            $existing,
            $programId,
            $appointmentId,
            $approvalReference,
            &$skipped,
            &$accepted,
            &$rightsConfirmed,
            &$failures,
        ): array {
            foreach ($inventory['parts'] as $part) {
                $hash = (string) $part['sha256'];
                $bytesExpected = (int) $part['bytes'];
                $level = (int) $part['level_number'];
                $partId = (string) $part['part_id'];
                $label = sprintf(
                    'L%d %s',
                    $level,
                    (string) $part['source_part_label'],
                );

                if (isset($existing[$hash])) {
                    $skipped++;
                    $assetId = (string) $existing[$hash];
                    if ($this->ensureRights(
                        $actor,
                        $programId,
                        $appointmentId,
                        $assetId,
                        $hash,
                        $approvalReference,
                    )) {
                        $rightsConfirmed++;
                    }

                    continue;
                }

                $path = $pathByHash[$hash] ?? null;
                if ($path === null || ! is_file($path)) {
                    $failures[] = [
                        'sha256' => $hash,
                        'safe_error_code' => 'staging_missing',
                    ];

                    continue;
                }

                $bytes = (string) file_get_contents($path);
                if (
                    strlen($bytes) !== $bytesExpected
                    || ! hash_equals(hash('sha256', $bytes), $hash)
                ) {
                    $failures[] = [
                        'sha256' => $hash,
                        'safe_error_code' => 'hash_mismatch',
                    ];

                    continue;
                }

                try {
                    $assetId = $this->uploadAccept($actor, $label, $bytes, $hash);
                    $accepted++;
                    $existing[$hash] = $assetId;
                    if ($this->ensureRights(
                        $actor,
                        $programId,
                        $appointmentId,
                        $assetId,
                        $hash,
                        $approvalReference,
                    )) {
                        $rightsConfirmed++;
                    }
                } catch (Throwable $exception) {
                    $code = 'ingest_failed';
                    if ($exception instanceof MediaLibraryRejected) {
                        $code = 'media_rejected';
                    } elseif ($exception instanceof MediaLibraryConflict) {
                        $code = 'media_conflict';
                    } elseif ($exception instanceof RuntimeException) {
                        $code = 'runtime_'.$exception->getMessage();
                        if (strlen($code) > 80) {
                            $code = 'ingest_failed';
                        }
                    }
                    $failures[] = [
                        'sha256' => $hash,
                        'safe_error_code' => $code,
                    ];
                }
            }

            return [
                'skipped_existing' => $skipped,
                'accepted' => $accepted,
                'rights_confirmed' => $rightsConfirmed,
                'failures' => $failures,
            ];
        });
    }

    private function uploadAccept(
        User $actor,
        string $safeLabel,
        string $bytes,
        string $hash,
    ): string {
        $started = $this->uploads->start(
            actor: $actor,
            assetKind: 'lesson_audio',
            safeLabel: $safeLabel,
            totalBytes: strlen($bytes),
            wholeObjectHash: $hash,
            idempotencyKey: (string) Str::ulid(),
        );
        $uploadId = $started->resultId;
        $version = 1;
        $partSize = (int) config('media.part_size_bytes', 1_048_576);
        $parts = (int) ceil(strlen($bytes) / $partSize);
        for ($n = 1; $n <= $parts; $n++) {
            $offset = ($n - 1) * $partSize;
            $chunk = substr($bytes, $offset, $partSize);
            $partHash = hash('sha256', $chunk);
            $result = $this->uploads->part(
                actor: $actor,
                uploadId: $uploadId,
                partNumber: $n,
                expectedUploadVersion: $version,
                partHash: $partHash,
                bytes: $chunk,
                idempotencyKey: (string) Str::ulid(),
            );
            $version = (int) ($result->data['upload_version'] ?? ($version + 1));
        }

        $finalized = $this->uploads->finalize(
            actor: $actor,
            uploadId: $uploadId,
            expectedUploadVersion: $version,
            idempotencyKey: (string) Str::ulid(),
        );
        $version = (int) ($finalized->data['upload_version'] ?? ($version + 1));
        if (($finalized->data['inspection_outcome'] ?? null) !== 'passed') {
            throw new MediaLibraryRejected;
        }
        // Prefer explicit clean; also accept scan_clean state.
        if (($finalized->data['malware_outcome'] ?? null) !== 'clean'
            && ($finalized->data['upload_state'] ?? null) !== 'scan_clean'
        ) {
            throw new MediaLibraryRejected;
        }

        $accepted = $this->uploads->accept(
            actor: $actor,
            uploadId: $uploadId,
            expectedUploadVersion: $version,
            idempotencyKey: (string) Str::ulid(),
        );
        $assetId = (string) ($accepted->data['media_asset_id'] ?? $accepted->resultId);
        if ($assetId === '') {
            throw new MediaLibraryConflict(MediaLibraryConflict::STALE_UPLOAD);
        }

        return $assetId;
    }

    private function ensureRights(
        User $actor,
        string $programId,
        string $appointmentId,
        string $mediaAssetId,
        string $selectedAssetHash,
        string $approvalReference,
    ): bool {
        $exists = DB::table('media_rights_revisions')
            ->where('media_asset_id', $mediaAssetId)
            ->where('evidence_status', 'approved')
            ->exists();
        if ($exists) {
            return false;
        }

        $this->rights->confirm(
            actor: $actor,
            programId: $programId,
            mediaAssetId: $mediaAssetId,
            selectedAssetHash: $selectedAssetHash,
            parentRevisionId: null,
            evidenceHash: hash('sha256', $approvalReference.'|'.$selectedAssetHash),
            rightsHolderReference: $approvalReference,
            provenanceReference: 'legacy-teaching-audio-inventory-v1',
            rightsBasisKey: 'owner_authorization',
            licenseOrContractReference: $approvalReference,
            permittedReleaseChannels: ['web', 'android'],
            permittedTerritories: ['IN'],
            learnerDeliveryAllowed: true,
            sharingAllowed: false,
            derivativeUseAllowed: true,
            effectiveAt: CarbonImmutable::now()->subMinute(),
            expiresAt: null,
            retentionPolicyKey: 'preserve_while_referenced',
            deletionConstraintKey: 'rights_review_required',
            authorityAppointmentId: $appointmentId,
            idempotencyKey: (string) Str::ulid(),
        );

        return true;
    }
}
