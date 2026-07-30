<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Models\Membership;
use App\Models\Organization;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class FoundationSeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_seed_preserves_the_exact_synthetic_draft(): void
    {
        $this->seed(FoundationSeeder::class);

        $before = [
            'counts' => collect([
                'organizations',
                'permissions',
                'roles',
                'programs',
                'curriculum_versions',
                'learning_objectives',
                'curriculum_modules',
                'lessons',
                'lesson_objectives',
                'content_documents',
                'content_revisions',
                'content_revision_heads',
                'learning_items',
                'curriculum_missions',
                'mission_items',
                'curriculum_graph_revisions',
                'curriculum_graph_heads',
            ])->mapWithKeys(
                static fn (string $table): array => [
                    $table => DB::table($table)->count(),
                ],
            )->all(),
            'program_id' => DB::table('programs')->value('id'),
            'version_id' => DB::table('curriculum_versions')->value('id'),
            'content_hash' => DB::table('content_revisions')
                ->value('content_hash'),
            'graph_hash' => DB::table('curriculum_graph_revisions')
                ->value('graph_hash'),
        ];

        $this->seed(FoundationSeeder::class);

        $this->assertSame(
            $before['counts'],
            collect(array_keys($before['counts']))
                ->mapWithKeys(
                    static fn (string $table): array => [
                        $table => DB::table($table)->count(),
                    ],
                )->all(),
        );
        $this->assertSame(
            $before['program_id'],
            DB::table('programs')->value('id'),
        );
        $this->assertSame(
            $before['version_id'],
            DB::table('curriculum_versions')->value('id'),
        );
        $this->assertSame(
            $before['content_hash'],
            DB::table('content_revisions')->value('content_hash'),
        );
        $this->assertSame(
            $before['graph_hash'],
            DB::table('curriculum_graph_revisions')->value('graph_hash'),
        );
        $this->assertSame(20, DB::table('permissions')->count());
        $this->assertSame(1, DB::table('curriculum_missions')->count());
        $this->assertSame(0, DB::table('curriculum_publications')->count());
    }

    public function test_conflicting_synthetic_program_key_fails_closed_and_rolls_back(): void
    {
        $now = now();
        $organizationId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'system_key' => Organization::DIRECT_SYSTEM_KEY,
            'name' => 'Global English Direct',
            'kind' => Organization::KIND_PLATFORM_DIRECT,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('programs')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'key' => 'synthetic-foundation-preview',
            'internal_name' => 'Conflicting Pre-existing Program',
            'created_by_membership_id' => null,
            'created_by_attribution' => 'untrusted-pre-existing-record',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $this->seed(FoundationSeeder::class);
            self::fail('Expected the conflicting synthetic program to fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Canonical foundation seed mismatch for programs.internal_name.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(0, DB::table('permissions')->count());
        $this->assertSame(0, DB::table('roles')->count());
        $this->assertSame(1, DB::table('programs')->count());
        $this->assertSame(
            'Conflicting Pre-existing Program',
            DB::table('programs')->value('internal_name'),
        );
    }

    public function test_pre_existing_publication_for_synthetic_draft_fails_closed(): void
    {
        $this->seed(FoundationSeeder::class);

        $now = now();
        $organizationId = (string) DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->value('id');
        $userId = (string) Str::ulid();
        $membershipId = (string) Str::ulid();
        $manifest = ['fixture' => 'pre-existing-publication'];

        DB::table('users')->insert([
            'id' => $userId,
            'display_name' => null,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_publications')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'program_id' => DB::table('programs')->value('id'),
            'curriculum_version_id' => DB::table('curriculum_versions')
                ->value('id'),
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => CanonicalJson::encode($manifest),
            'manifest_hash' => CanonicalJson::sha256($manifest),
            'created_by_membership_id' => $membershipId,
            'created_at' => $now,
        ]);

        try {
            $this->seed(FoundationSeeder::class);
            self::fail('Expected the published synthetic draft to fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The synthetic foundation draft already has a publication and cannot be adopted by the foundation seed.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(1, DB::table('curriculum_publications')->count());
        $this->assertSame(1, DB::table('curriculum_graph_revisions')->count());
    }
}
