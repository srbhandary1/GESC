<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Curriculum\CanonicalJson;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * APP-0063 private Direct auto-commission for Levels 2–17.
 * Provisional English/Kannada approvals — not specialist bilingual review.
 */
final class App0063LevelsCommissioner
{
    private const string APPROVAL = 'APP-0063';

    private const string ORG_ID = '01KYS97G7MX374P303XJHDXT72';

    private const string PROGRAM_ID = '01KZNK3BWD8A8NJ5A5JW0ZVZMT';

    private const string CURRICULUM_VERSION_ID = '01KZZY3RFJGXQENRM5YBEWE67C';

    private const string ACTOR_MEMBERSHIP_ID = '01KZGE9QRTCQE3PQ3RN31AXQSF';

    public function __construct(
        private readonly LessonPracticeAttachmentService $attachment,
        private readonly OrganizationContextManager $contexts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function commission(string $inventoryPath): array
    {
        if (! is_file($inventoryPath)) {
            throw new RuntimeException('inventory_missing');
        }

        /** @var array{parts?: list<array<string, mixed>>} $inventory */
        $inventory = json_decode(
            (string) file_get_contents($inventoryPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $parts = $inventory['parts'] ?? [];
        if ($parts === []) {
            throw new RuntimeException('inventory_empty');
        }

        $byLevel = [];
        foreach ($parts as $part) {
            $level = (int) ($part['level_number'] ?? 0);
            if ($level < 2 || $level > 17) {
                continue;
            }
            $partId = (string) ($part['part_id'] ?? '');
            $hash = (string) ($part['sha256'] ?? '');
            $concept = (string) ($part['concept_key'] ?? '');
            if ($partId === '' || $hash === '' || $concept === '') {
                throw new RuntimeException('inventory_row_invalid');
            }
            $byLevel[$level]['concept_key'] = $concept;
            $byLevel[$level]['hashes'][$partId] = $hash;
        }

        $actor = User::query()
            ->whereKey(
                (string) DB::table('memberships')
                    ->where('id', self::ACTOR_MEMBERSHIP_ID)
                    ->value('user_id'),
            )
            ->firstOrFail();

        $context = new OrganizationContext(
            self::ORG_ID,
            (string) $actor->getKey(),
            self::ACTOR_MEMBERSHIP_ID,
            OrganizationContextSource::Membership,
        );

        $levels = [];
        $this->contexts->run($context, function () use (
            $byLevel,
            &$levels,
        ): void {
            foreach ($byLevel as $levelNumber => $row) {
                $levels[] = $this->commissionLevel(
                    (int) $levelNumber,
                    (string) $row['concept_key'],
                    $row['hashes'],
                );
            }
        });

        ksort($levels);

        return [
            'approval_reference' => self::APPROVAL,
            'organization_id' => self::ORG_ID,
            'program_id' => self::PROGRAM_ID,
            'levels_commissioned' => count($levels),
            'levels' => array_values($levels),
        ];
    }

    /**
     * @param  array<string, string>  $hashes
     * @return array<string, mixed>
     */
    private function commissionLevel(
        int $levelNumber,
        string $conceptKey,
        array $hashes,
    ): array {
        $ordered = $this->resolveFourParts($levelNumber, $hashes);
        $assets = [];
        foreach ($ordered as $partId => $hash) {
            $assets[$partId] = $this->ensureAssetBinding($levelNumber, $partId, $conceptKey, $hash);
        }

        $canonical = AuthoredFoundationLessons::blueprint(
            $levelNumber,
            $conceptKey,
            $assets,
        );
        $created = $this->attachment->createBlueprint(
            $this->contexts->current(),
            self::PROGRAM_ID,
            self::CURRICULUM_VERSION_ID,
            $conceptKey,
            $canonical,
            (string) Str::ulid(),
        );
        $blueprint = $created['blueprint'];
        $hash = (string) $blueprint->content_hash;

        $english = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'english_academic',
            'approved',
            $hash,
            (string) Str::ulid(),
        );
        $kannada = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'kannada',
            'approved',
            $hash,
            (string) Str::ulid(),
        );

        $attachParts = [];
        $order = 1;
        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $partId) {
            $asset = $assets[$partId];
            $attachParts[] = [
                'order' => $order,
                'part_id' => $partId,
                'asset_id' => $asset['media_asset_id'],
                'metadata_revision_id' => $asset['metadata_revision_id'],
                'metadata_content_hash' => $asset['metadata_content_hash'],
                'rights_revision_id' => $asset['rights_revision_id'],
            ];
            $order++;
        }

        $attached = $this->attachment->attachParts(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            $hash,
            $attachParts,
            (string) Str::ulid(),
        );

        return [
            'level_number' => $levelNumber,
            'concept_key' => $conceptKey,
            'blueprint_id' => (string) $blueprint->getKey(),
            'content_hash' => $hash,
            'blueprint_created' => $created['created'],
            'english_created' => $english['created'],
            'kannada_created' => $kannada['created'],
            'attach_created' => $attached['created'],
            'parts' => array_map(
                static fn (array $part): array => [
                    'part_id' => $part['part_id'],
                    'media_asset_id' => $part['asset_id'],
                ],
                $attachParts,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $hashes
     * @return array<string, string>
     */
    private function resolveFourParts(int $levelNumber, array $hashes): array
    {
        $required = ['introduction', 'practice', 'conversation', 'interaction'];
        $resolved = [];
        foreach ($required as $partId) {
            if (isset($hashes[$partId])) {
                $resolved[$partId] = $hashes[$partId];

                continue;
            }
            if ($levelNumber === 15 && $partId === 'practice' && isset($hashes['introduction'])) {
                // Inventory combines Introduction/Practice into one file.
                $resolved[$partId] = $hashes['introduction'];

                continue;
            }
            throw new RuntimeException('part_hash_missing:'.$levelNumber.':'.$partId);
        }

        return $resolved;
    }

    /**
     * @return array{
     *   media_asset_id: string,
     *   metadata_revision_id: string,
     *   metadata_content_hash: string,
     *   rights_revision_id: string
     * }
     */
    private function ensureAssetBinding(
        int $levelNumber,
        string $partId,
        string $conceptKey,
        string $hash,
    ): array {
        $asset = DB::table('media_assets')
            ->where('organization_id', self::ORG_ID)
            ->where('asset_kind', 'lesson_audio')
            ->where('asset_hash', $hash)
            ->first();
        if ($asset === null) {
            throw new RuntimeException('asset_missing:'.$hash);
        }
        $assetId = (string) $asset->id;

        $active = DB::table('media_asset_lifecycles')
            ->where('organization_id', self::ORG_ID)
            ->where('media_asset_id', $assetId)
            ->where('state', 'active')
            ->exists();
        if (! $active) {
            throw new RuntimeException('asset_inactive:'.$assetId);
        }

        $rights = DB::table('media_rights_revisions')
            ->where('organization_id', self::ORG_ID)
            ->where('media_asset_id', $assetId)
            ->where('evidence_status', 'approved')
            ->orderByDesc('created_at')
            ->first();
        if ($rights === null) {
            throw new RuntimeException('rights_missing:'.$assetId);
        }

        $head = DB::table('media_asset_metadata_heads')
            ->where('organization_id', self::ORG_ID)
            ->where('media_asset_id', $assetId)
            ->first();
        if ($head !== null) {
            $meta = DB::table('media_asset_metadata_revisions')
                ->where('organization_id', self::ORG_ID)
                ->where('id', $head->metadata_revision_id)
                ->first();
            if ($meta === null) {
                throw new RuntimeException('metadata_orphan:'.$assetId);
            }

            return [
                'media_asset_id' => $assetId,
                'metadata_revision_id' => (string) $meta->id,
                'metadata_content_hash' => (string) $meta->content_hash,
                'rights_revision_id' => (string) $rights->id,
            ];
        }

        $label = sprintf(
            'Level %d — %s — %s (APP-0063 provisional)',
            $levelNumber,
            $conceptKey,
            ucfirst($partId),
        );
        $transcript = sprintf(
            'Provisional APP-0063 placeholder transcript for Level %d concept %s part %s. Real transcript and bilingual review remain outstanding.',
            $levelNumber,
            $conceptKey,
            $partId,
        );
        $contentHash = CanonicalJson::sha256([
            'asset_kind' => 'lesson_audio',
            'locale' => 'en-IN',
            'operator_label' => $label,
            'text_alternative' => null,
            'transcript' => $transcript,
        ]);
        $revisionId = (string) Str::ulid();
        $now = now();
        DB::table('media_asset_metadata_revisions')->insert([
            'id' => $revisionId,
            'organization_id' => self::ORG_ID,
            'media_asset_id' => $assetId,
            'asset_kind' => 'lesson_audio',
            'parent_revision_id' => null,
            'revision_number' => 1,
            'operator_label' => $label,
            'transcript' => $transcript,
            'text_alternative' => null,
            'content_hash' => $contentHash,
            'authored_by_membership_id' => self::ACTOR_MEMBERSHIP_ID,
            'authored_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_metadata_heads')->insert([
            'organization_id' => self::ORG_ID,
            'media_asset_id' => $assetId,
            'metadata_revision_id' => $revisionId,
            'version' => 1,
            'updated_by_membership_id' => self::ACTOR_MEMBERSHIP_ID,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'media_asset_id' => $assetId,
            'metadata_revision_id' => $revisionId,
            'metadata_content_hash' => $contentHash,
            'rights_revision_id' => (string) $rights->id,
        ];
    }
}
