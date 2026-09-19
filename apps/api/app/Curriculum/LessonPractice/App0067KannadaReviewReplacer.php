<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Models\LessonPracticeBlueprint;
use App\Models\LessonPracticeMedia;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use RuntimeException;

/**
 * APP-0067: record named Kannada review of APP-0065 authored Levels 2–17
 * drafts after APP-0066 English review. Publication remains blocked.
 */
final class App0067KannadaReviewReplacer
{
    private const string APPROVAL = 'APP-0067';

    /** @var array<string, string> */
    private array $scope = [];

    public function __construct(
        private readonly LessonPracticeAttachmentService $attachment,
        private readonly OrganizationContextManager $contexts,
        private readonly DirectPathwayScopeResolver $scopeResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function replace(): array
    {
        $this->scope = $this->scopeResolver->resolve();
        $actor = User::query()
            ->whereKey($this->scope['actor_user_id'])
            ->firstOrFail();

        $context = new OrganizationContext(
            $this->scope['organization_id'],
            (string) $actor->getKey(),
            $this->scope['actor_membership_id'],
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
            'organization_id' => $this->scope['organization_id'],
            'program_id' => $this->scope['program_id'],
            'levels_replaced' => count($levels),
            'kannada_independent_review' => true,
            'publication_eligible' => false,
            'levels' => $levels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function replaceLevel(int $levelNumber, string $conceptKey): array
    {
        $current = LessonPracticeBlueprint::query()
            ->where('organization_id', $this->scope['organization_id'])
            ->where('program_id', $this->scope['program_id'])
            ->where('concept_key', $conceptKey)
            ->whereIn('state', ['draft', 'published'])
            ->orderByDesc('revision_number')
            ->first();
        if ($current === null) {
            throw new RuntimeException('blueprint_missing:'.$conceptKey);
        }

        $media = LessonPracticeMedia::query()
            ->where('organization_id', $this->scope['organization_id'])
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
            $this->scope['program_id'],
            $this->scope['curriculum_version_id'],
            $conceptKey,
            $canonical,
            'lpl04-checkpoint-app0067-create-'.$conceptKey,
        );
        $blueprint = $created['blueprint'];
        $hash = (string) $blueprint->content_hash;

        $english = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'english_academic',
            'approved',
            $hash,
            'lpl04-checkpoint-app0067-en-'.$conceptKey,
        );
        $kannada = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'kannada',
            'approved',
            $hash,
            'lpl04-checkpoint-app0067-kn-'.$conceptKey,
        );
        $attached = $this->attachment->attachParts(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            $hash,
            $attachParts,
            'lpl04-checkpoint-app0067-attach-'.$conceptKey,
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
            'kannada_independent' => true,
            'attach_created' => $attached['created'],
        ];
    }
}
