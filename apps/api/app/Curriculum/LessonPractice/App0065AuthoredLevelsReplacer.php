<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Models\LessonPracticeBlueprint;
use App\Models\LessonPracticeMedia;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * APP-0065: replace APP-0063 provisional Level 2–17 blueprint text with authored
 * revisions, keeping the same four attached lesson_audio parts.
 */
final class App0065AuthoredLevelsReplacer
{
    private const string APPROVAL = 'APP-0065';

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
    public function replace(): array
    {
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

        $concepts = config('level_sessions.level_concepts', []);
        $levels = [];
        $this->contexts->run($context, function () use ($concepts, &$levels): void {
            foreach ($concepts as $levelNumber => $conceptKey) {
                $levelNumber = (int) $levelNumber;
                if ($levelNumber < 2 || $levelNumber > 17 || ! is_string($conceptKey)) {
                    continue;
                }
                $levels[] = $this->replaceLevel($levelNumber, $conceptKey);
            }
        });

        usort(
            $levels,
            static fn (array $left, array $right): int => $left['level_number'] <=> $right['level_number'],
        );

        return [
            'approval_reference' => self::APPROVAL,
            'organization_id' => self::ORG_ID,
            'program_id' => self::PROGRAM_ID,
            'levels_replaced' => count($levels),
            'levels' => $levels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function replaceLevel(int $levelNumber, string $conceptKey): array
    {
        $current = LessonPracticeBlueprint::query()
            ->where('organization_id', self::ORG_ID)
            ->where('program_id', self::PROGRAM_ID)
            ->where('concept_key', $conceptKey)
            ->whereIn('state', ['draft', 'published'])
            ->orderByDesc('revision_number')
            ->first();
        if ($current === null) {
            throw new RuntimeException('blueprint_missing:'.$conceptKey);
        }

        $media = LessonPracticeMedia::query()
            ->where('organization_id', self::ORG_ID)
            ->where('blueprint_id', $current->getKey())
            ->orderBy('part_order')
            ->get();
        if ($media->count() !== 4) {
            throw new RuntimeException('parts_missing:'.$conceptKey);
        }

        $assets = [];
        $attachParts = [];
        foreach ($media as $row) {
            $partId = (string) $row->part_id;
            $assets[$partId] = [
                'media_asset_id' => (string) $row->media_asset_id,
                'metadata_revision_id' => (string) $row->metadata_revision_id,
                'metadata_content_hash' => (string) $row->metadata_content_hash,
                'rights_revision_id' => (string) $row->media_rights_revision_id,
            ];
            $attachParts[] = [
                'order' => (int) $row->part_order,
                'part_id' => $partId,
                'asset_id' => (string) $row->media_asset_id,
                'metadata_revision_id' => (string) $row->metadata_revision_id,
                'metadata_content_hash' => (string) $row->metadata_content_hash,
                'rights_revision_id' => (string) $row->media_rights_revision_id,
            ];
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
            'lpl04-checkpoint-app0065-create-'.$conceptKey,
        );
        $blueprint = $created['blueprint'];
        $hash = (string) $blueprint->content_hash;

        $english = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'english_academic',
            'approved',
            $hash,
            'lpl04-checkpoint-app0065-en-'.$conceptKey,
        );
        $kannada = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'kannada',
            'approved',
            $hash,
            'lpl04-checkpoint-app0065-kn-'.$conceptKey,
        );
        $attached = $this->attachment->attachParts(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            $hash,
            $attachParts,
            'lpl04-checkpoint-app0065-attach-'.$conceptKey,
        );

        return [
            'level_number' => $levelNumber,
            'concept_key' => $conceptKey,
            'previous_blueprint_id' => (string) $current->getKey(),
            'blueprint_id' => (string) $blueprint->getKey(),
            'revision_number' => (int) $blueprint->revision_number,
            'content_hash' => $hash,
            'blueprint_created' => $created['created'],
            'english_created' => $english['created'],
            'kannada_created' => $kannada['created'],
            'attach_created' => $attached['created'],
        ];
    }
}
