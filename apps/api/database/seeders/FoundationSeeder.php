<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Models\Organization;
use App\Models\Role;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class FoundationSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const array PERMISSIONS = [
        'cohorts.manage' => 'Create and manage organization cohorts.',
        'cohorts.read' => 'View organization cohorts.',
        'enrollments.manage' => 'Create and manage organization enrollments.',
        'enrollments.read' => 'View organization enrollments.',
        'institution_invites.manage' => 'Issue and revoke institution invitations.',
        'memberships.manage' => 'Manage organization memberships.',
        'memberships.read' => 'View organization memberships.',
        'roles.manage' => 'Manage organization roles and permission grants.',
        'curriculum.author' => 'Create organization-owned curriculum drafts and revisions.',
        'curriculum.translate.kn' => 'Create Kannada variants for organization curriculum.',
        'curriculum.review.en' => 'Record appointed English academic review evidence.',
        'curriculum.review.kn' => 'Record appointed Kannada review evidence.',
        'curriculum.publish' => 'Publish and roll back reviewed curriculum releases.',
        'curriculum.share.manage' => 'Issue and withdraw curriculum share grants.',
        'curriculum.shared.read' => 'Preview explicitly shared curriculum releases.',
        'curriculum.shared.clone' => 'Clone an explicitly shared release into an organization draft.',
        'enrollments.curriculum.assign' => 'Pin organization enrollments to an authorized publication.',
        'curriculum.authority.manage' => 'Manage scoped curriculum authority appointments.',
        'media.rights.approve' => 'Confirm media-rights evidence under an appointment.',
        'curriculum.suspend' => 'Suspend or resume organization curriculum delivery.',
        'learning.participate' => 'Use assigned learner missions and record self-owned learning evidence.',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('organizations')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'system_key' => Organization::DIRECT_SYSTEM_KEY,
                'name' => 'Global English Direct',
                'kind' => Organization::KIND_PLATFORM_DIRECT,
                'status' => Organization::STATUS_ACTIVE,
                'timezone' => 'Asia/Kolkata',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $organizationId = $this->requiredId('organizations', [
                'system_key' => Organization::DIRECT_SYSTEM_KEY,
            ]);
            $this->assertCanonicalRecord(
                'organizations',
                ['id' => $organizationId],
                [
                    'system_key' => Organization::DIRECT_SYSTEM_KEY,
                    'name' => 'Global English Direct',
                    'kind' => Organization::KIND_PLATFORM_DIRECT,
                    'status' => Organization::STATUS_ACTIVE,
                    'timezone' => 'Asia/Kolkata',
                ],
            );

            foreach (self::PERMISSIONS as $key => $description) {
                DB::table('permissions')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'key' => $key,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->assertCanonicalRecord(
                    'permissions',
                    ['key' => $key],
                    [
                        'key' => $key,
                        'description' => $description,
                    ],
                );
            }

            DB::table('roles')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'key' => Role::DIRECT_LEARNER_KEY,
                'name' => 'Learner',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->assertCanonicalRecord(
                'roles',
                [
                    'organization_id' => $organizationId,
                    'key' => Role::DIRECT_LEARNER_KEY,
                ],
                [
                    'organization_id' => $organizationId,
                    'key' => Role::DIRECT_LEARNER_KEY,
                    'name' => 'Learner',
                    'is_system' => true,
                ],
            );

            $learnerRoleId = $this->requiredId('roles', [
                'organization_id' => $organizationId,
                'key' => Role::DIRECT_LEARNER_KEY,
            ]);
            $learningPermissionId = $this->requiredId('permissions', [
                'key' => 'learning.participate',
            ]);
            DB::table('role_permissions')->insertOrIgnore([
                'organization_id' => $organizationId,
                'role_id' => $learnerRoleId,
                'permission_id' => $learningPermissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->assertCanonicalRecord(
                'role_permissions',
                [
                    'organization_id' => $organizationId,
                    'role_id' => $learnerRoleId,
                    'permission_id' => $learningPermissionId,
                ],
                [
                    'organization_id' => $organizationId,
                    'role_id' => $learnerRoleId,
                    'permission_id' => $learningPermissionId,
                ],
            );

            $this->seedSyntheticDraft($organizationId, $now);
        });
    }

    private function seedSyntheticDraft(
        string $organizationId,
        CarbonInterface $now,
    ): void {
        $attribution = 'codex_synthetic_draft_pending_human_review';

        DB::table('programs')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'key' => 'synthetic-foundation-preview',
            'internal_name' => 'Synthetic Foundation Preview',
            'created_by_membership_id' => null,
            'created_by_attribution' => $attribution,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $programId = $this->requiredId('programs', [
            'organization_id' => $organizationId,
            'key' => 'synthetic-foundation-preview',
        ]);
        $this->assertCanonicalRecord(
            'programs',
            ['id' => $programId],
            [
                'organization_id' => $organizationId,
                'key' => 'synthetic-foundation-preview',
                'internal_name' => 'Synthetic Foundation Preview',
                'created_by_membership_id' => null,
                'created_by_attribution' => $attribution,
            ],
        );

        DB::table('curriculum_versions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => 'draft-1',
            'created_by_membership_id' => null,
            'created_by_attribution' => $attribution,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $versionId = $this->requiredId('curriculum_versions', [
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => 'draft-1',
        ]);
        $this->assertCanonicalRecord(
            'curriculum_versions',
            ['id' => $versionId],
            [
                'organization_id' => $organizationId,
                'program_id' => $programId,
                'key' => 'draft-1',
                'created_by_membership_id' => null,
                'created_by_attribution' => $attribution,
            ],
        );

        if (
            DB::table('curriculum_publications')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $versionId)
                ->exists()
        ) {
            throw new RuntimeException(
                'The synthetic foundation draft already has a publication and cannot be adopted by the foundation seed.',
            );
        }

        DB::table('learning_objectives')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'introduce-yourself',
            'outcome_code' => 'synthetic.introduction',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $objectiveId = $this->requiredId('learning_objectives', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'introduce-yourself',
        ]);
        $this->assertCanonicalRecord(
            'learning_objectives',
            ['id' => $objectiveId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'key' => 'introduce-yourself',
                'outcome_code' => 'synthetic.introduction',
                'sequence' => 1,
            ],
        );

        DB::table('curriculum_modules')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'synthetic-introductions',
            'sequence' => 1,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $moduleId = $this->requiredId('curriculum_modules', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'synthetic-introductions',
        ]);
        $this->assertCanonicalRecord(
            'curriculum_modules',
            ['id' => $moduleId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'key' => 'synthetic-introductions',
                'sequence' => 1,
                'estimated_minutes' => 5,
            ],
        );

        DB::table('lessons')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'synthetic-first-introduction',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $lessonId = $this->requiredId('lessons', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'synthetic-first-introduction',
        ]);
        $this->assertCanonicalRecord(
            'lessons',
            ['id' => $lessonId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'curriculum_module_id' => $moduleId,
                'key' => 'synthetic-first-introduction',
                'sequence' => 1,
            ],
        );

        DB::table('lesson_objectives')->insertOrIgnore([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertCanonicalRecord(
            'lesson_objectives',
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'lesson_id' => $lessonId,
                'objective_id' => $objectiveId,
            ],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'lesson_id' => $lessonId,
                'objective_id' => $objectiveId,
            ],
        );

        DB::table('content_documents')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'synthetic-introduction-instruction',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $documentId = $this->requiredId('content_documents', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'synthetic-introduction-instruction',
        ]);
        $this->assertCanonicalRecord(
            'content_documents',
            ['id' => $documentId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'key' => 'synthetic-introduction-instruction',
            ],
        );
        $payload = [
            'fixture_notice' => 'Synthetic draft for development review only.',
            'instruction' => 'Introduce yourself using a greeting and your name.',
        ];
        $contentHash = CanonicalJson::sha256($payload);

        DB::table('content_revisions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'payload' => CanonicalJson::encode($payload),
            'content_hash' => $contentHash,
            'authored_by_membership_id' => null,
            'author_attribution' => $attribution,
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $revisionId = $this->requiredId('content_revisions', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'revision_number' => 1,
        ]);
        $this->assertCanonicalRecord(
            'content_revisions',
            ['id' => $revisionId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'content_document_id' => $documentId,
                'parent_revision_id' => null,
                'revision_number' => 1,
                'source_locale' => 'en-IN',
                'content_type' => 'instruction',
                'learner_visible' => true,
                'localization_requirement' => 'kn_required',
                'pedagogically_authoritative' => true,
                'payload' => $payload,
                'content_hash' => $contentHash,
                'authored_by_membership_id' => null,
                'author_attribution' => $attribution,
            ],
        );

        DB::table('content_revision_heads')->insertOrIgnore([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_revision_id' => $revisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertCanonicalRecord(
            'content_revision_heads',
            [
                'organization_id' => $organizationId,
                'content_document_id' => $documentId,
            ],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'content_document_id' => $documentId,
                'content_revision_id' => $revisionId,
            ],
        );

        DB::table('learning_items')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'content_document_id' => $documentId,
            'key' => 'synthetic-introduction-prompt',
            'item_type' => 'speaking_prompt',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $itemId = $this->requiredId('learning_items', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'synthetic-introduction-prompt',
        ]);
        $this->assertCanonicalRecord(
            'learning_items',
            ['id' => $itemId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'lesson_id' => $lessonId,
                'content_document_id' => $documentId,
                'key' => 'synthetic-introduction-prompt',
                'item_type' => 'speaking_prompt',
                'sequence' => 1,
            ],
        );

        DB::table('curriculum_missions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'instruction_content_document_id' => $documentId,
            'key' => 'synthetic-introduction-mission',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $missionId = $this->requiredId('curriculum_missions', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'synthetic-introduction-mission',
        ]);
        $this->assertCanonicalRecord(
            'curriculum_missions',
            ['id' => $missionId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'instruction_content_document_id' => $documentId,
                'key' => 'synthetic-introduction-mission',
                'sequence' => 1,
            ],
        );

        DB::table('mission_items')->insertOrIgnore([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertCanonicalRecord(
            'mission_items',
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'mission_id' => $missionId,
                'learning_item_id' => $itemId,
            ],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'mission_id' => $missionId,
                'learning_item_id' => $itemId,
                'sequence' => 1,
            ],
        );

        $projection = app(CurriculumGraphProjector::class)->project(
            $organizationId,
            $versionId,
        );

        DB::table('curriculum_graph_revisions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($projection['graph']),
            'graph_hash' => $projection['graph_hash'],
            'authored_by_membership_id' => null,
            'author_attribution' => $attribution,
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $graphRevisionId = $this->requiredId(
            'curriculum_graph_revisions',
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'revision_number' => 1,
            ],
        );
        $this->assertCanonicalRecord(
            'curriculum_graph_revisions',
            ['id' => $graphRevisionId],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'parent_revision_id' => null,
                'parent_revision_number' => null,
                'revision_number' => 1,
                'graph' => $projection['graph'],
                'graph_hash' => $projection['graph_hash'],
                'authored_by_membership_id' => null,
                'author_attribution' => $attribution,
            ],
        );

        DB::table('curriculum_graph_heads')->insertOrIgnore([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projection['graph_hash'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertCanonicalRecord(
            'curriculum_graph_heads',
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
            ],
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $versionId,
                'curriculum_graph_revision_id' => $graphRevisionId,
                'graph_hash' => $projection['graph_hash'],
            ],
        );
    }

    /**
     * @param  array<string, string|int>  $where
     */
    private function requiredId(string $table, array $where): string
    {
        $id = DB::table($table)->where($where)->value('id');

        if (! is_string($id)) {
            throw new RuntimeException(sprintf(
                'Unable to resolve the seeded %s record.',
                $table,
            ));
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $expected
     */
    private function assertCanonicalRecord(
        string $table,
        array $where,
        array $expected,
    ): void {
        $record = DB::table($table)->where($where)->first();

        if ($record === null) {
            throw new RuntimeException(sprintf(
                'Unable to resolve the canonical %s foundation record.',
                $table,
            ));
        }

        $values = get_object_vars($record);

        foreach ($expected as $column => $canonicalValue) {
            if (
                ! array_key_exists($column, $values)
                || ! $this->canonicalValuesMatch(
                    $values[$column],
                    $canonicalValue,
                )
            ) {
                throw new RuntimeException(sprintf(
                    'Canonical foundation seed mismatch for %s.%s.',
                    $table,
                    $column,
                ));
            }
        }
    }

    private function canonicalValuesMatch(
        mixed $actual,
        mixed $expected,
    ): bool {
        if (is_array($expected)) {
            if (is_string($actual)) {
                try {
                    $actual = json_decode(
                        $actual,
                        true,
                        flags: JSON_THROW_ON_ERROR,
                    );
                } catch (JsonException) {
                    return false;
                }
            }

            if (! is_array($actual)) {
                return false;
            }

            try {
                return CanonicalJson::encode($actual)
                    === CanonicalJson::encode($expected);
            } catch (JsonException) {
                return false;
            }
        }

        if (is_bool($expected)) {
            $canonicalBooleans = $expected
                ? [true, 1, '1', 't', 'true']
                : [false, 0, '0', 'f', 'false'];

            return in_array($actual, $canonicalBooleans, true);
        }

        if (is_int($expected)) {
            return (is_int($actual) || is_string($actual))
                && is_numeric($actual)
                && (int) $actual === $expected;
        }

        return $actual === $expected;
    }
}
