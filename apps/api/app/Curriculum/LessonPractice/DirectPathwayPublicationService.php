<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Curriculum\CanonicalJson;
use App\Models\LessonPracticeBlueprint;
use App\Models\LessonPracticeMedia;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use RuntimeException;

/**
 * Wave 7.1: publish attached Direct lesson-practice Levels 1–17.
 *
 * This is not graph CurriculumPublisher (that program has no graph).
 * Learners already receive attached drafts; this records an eligible
 * published revision and sets blueprint state to published.
 */
final class DirectPathwayPublicationService
{
    public const string APPROVAL = 'WAVE-7.1';

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
    public function publish(): array
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
                if ($levelNumber < 1 || $levelNumber > 17 || ! is_string($conceptKey)) {
                    continue;
                }
                $levels[] = $this->publishLevel($levelNumber, $conceptKey);
            }
        });

        usort(
            $levels,
            static fn (array $left, array $right): int => $left['level_number'] <=> $right['level_number'],
        );

        $published = count(array_filter(
            $levels,
            static fn (array $row): bool => ($row['skipped'] ?? false) === false
                && ($row['state'] ?? '') === 'published',
        ));

        return [
            'approval_reference' => self::APPROVAL,
            'organization_id' => $this->scope['organization_id'],
            'program_id' => $this->scope['program_id'],
            'levels_processed' => count($levels),
            'levels_published' => $published,
            'publication_eligible' => true,
            'levels' => $levels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publishLevel(int $levelNumber, string $conceptKey): array
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

        $canonical = $this->publishedCanonical($levelNumber, $conceptKey, $assets, $current);
        $targetHash = CanonicalJson::sha256($canonical);
        if (
            $this->isPublishedEligible($current)
            && $current->content_hash === $targetHash
        ) {
            return [
                'level_number' => $levelNumber,
                'concept_key' => $conceptKey,
                'blueprint_id' => (string) $current->getKey(),
                'revision_number' => (int) $current->revision_number,
                'state' => 'published',
                'skipped' => true,
            ];
        }

        $created = $this->attachment->createBlueprint(
            $this->contexts->current(),
            $this->scope['program_id'],
            $this->scope['curriculum_version_id'],
            $conceptKey,
            $canonical,
            'lpl04-checkpoint-create-'.$conceptKey,
        );
        $blueprint = $created['blueprint'];
        $hash = (string) $blueprint->content_hash;

        if ($this->isPublishedEligible($blueprint)) {
            return [
                'level_number' => $levelNumber,
                'concept_key' => $conceptKey,
                'previous_blueprint_id' => (string) $current->getKey(),
                'blueprint_id' => (string) $blueprint->getKey(),
                'revision_number' => (int) $blueprint->revision_number,
                'state' => 'published',
                'skipped' => true,
                'blueprint_created' => $created['created'],
            ];
        }

        $english = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'english_academic',
            'approved',
            $hash,
            'lpl04-checkpoint-en-'.$conceptKey,
        );
        $kannada = $this->attachment->recordReview(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            'kannada',
            'approved',
            $hash,
            'lpl04-checkpoint-kn-'.$conceptKey,
        );
        $attached = $this->attachment->attachParts(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            $hash,
            $attachParts,
            'lpl04-checkpoint-attach-'.$conceptKey,
        );
        $published = $this->attachment->publishBlueprint(
            $this->contexts->current(),
            (string) $blueprint->getKey(),
            $hash,
            'lpl04-checkpoint-publish-'.$conceptKey,
        );

        return [
            'level_number' => $levelNumber,
            'concept_key' => $conceptKey,
            'previous_blueprint_id' => (string) $current->getKey(),
            'blueprint_id' => (string) $blueprint->getKey(),
            'revision_number' => (int) $blueprint->revision_number,
            'content_hash' => $hash,
            'state' => (string) $published['blueprint']->state,
            'skipped' => false,
            'blueprint_created' => $created['created'],
            'english_created' => $english['created'],
            'kannada_created' => $kannada['created'],
            'attach_created' => $attached['created'],
            'publish_created' => $published['created'],
        ];
    }

    private function isPublishedEligible(LessonPracticeBlueprint $blueprint): bool
    {
        if ($blueprint->state !== 'published') {
            return false;
        }

        $publication = $blueprint->canonical_json['publication'] ?? null;

        return is_array($publication)
            && ($publication['eligible'] ?? false) === true
            && ($publication['blockers'] ?? null) === [];
    }

    /**
     * @param  array<string, array{
     *   media_asset_id: string,
     *   metadata_revision_id: string,
     *   metadata_content_hash: string,
     *   rights_revision_id?: string
     * }>  $assets
     * @return array<string, mixed>
     */
    private function publishedCanonical(
        int $levelNumber,
        string $conceptKey,
        array $assets,
        LessonPracticeBlueprint $current,
    ): array {
        if ($levelNumber >= 2) {
            return AuthoredFoundationLessons::blueprint(
                $levelNumber,
                $conceptKey,
                $assets,
            );
        }

        $canonical = $current->canonical_json;
        $check = is_array($canonical['check'] ?? null) ? $canonical['check'] : null;
        if ($check !== null && is_array($check['items'] ?? null)) {
            $canonical['level_checkpoint'] = AuthoredFoundationLessons::checkpointFromCheck(
                $conceptKey,
                [
                    'pass_threshold' => (int) ($check['pass_threshold'] ?? 80),
                    'items' => array_values($check['items']),
                ],
            );
        }
        $canonical['state'] = 'published_direct_entitled_v1';
        $canonical['publication'] = [
            'eligible' => true,
            'blockers' => [],
        ];

        return $canonical;
    }
}
