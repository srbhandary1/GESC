<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Support\RoleplayCatalog;
use App\Curriculum\CanonicalJson;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds synthetic Role-play catalog personas/scenarios for an organization.
 */
final class RoleplayCatalogSeeder
{
    /**
     * @return array{seeded: int, skipped: int}
     */
    public function seedOrganization(
        string $organizationId,
        CarbonInterface $now,
    ): array {
        $seeded = 0;
        $skipped = 0;

        foreach (RoleplayCatalog::entries() as $entry) {
            if ($this->alreadySeeded($organizationId, $entry['persona_key'], $entry['scenario_key'])) {
                $skipped++;

                continue;
            }

            $this->seedEntry($organizationId, $now, $entry);
            $seeded++;
        }

        return ['seeded' => $seeded, 'skipped' => $skipped];
    }

    private function alreadySeeded(
        string $organizationId,
        string $personaKey,
        string $scenarioKey,
    ): bool {
        return DB::table('ai_personas')
            ->where('organization_id', $organizationId)
            ->where('key', $personaKey)
            ->exists()
            && DB::table('roleplay_scenarios')
                ->where('organization_id', $organizationId)
                ->where('key', $scenarioKey)
                ->exists();
    }

    /**
     * @param  array{
     *   id: string,
     *   display_name: string,
     *   persona_key: string,
     *   scenario_key: string,
     *   topic_code: string,
     *   style_code: string,
     *   response_code: string,
     *   objectives: list<array{
     *     objective_key: string,
     *     title: string,
     *     completion_criteria_code: string,
     *     sort_order: int
     *   }>
     * }  $entry
     */
    private function seedEntry(
        string $organizationId,
        CarbonInterface $now,
        array $entry,
    ): void {
        $attribution = 'codex_synthetic_fake_pending_human_review';
        $personaDefinition = [
            'disclosure' => 'I am a deterministic AI role-play prototype, not a human.',
            'display_name' => $entry['display_name'].' Role-play Prototype',
            'style_code' => $entry['style_code'],
        ];
        $personaHash = CanonicalJson::sha256($personaDefinition);

        DB::table('ai_personas')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'key' => $entry['persona_key'],
            'status' => 'development_only',
            'created_by_attribution' => $attribution,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $personaId = $this->requiredId('ai_personas', [
            'organization_id' => $organizationId,
            'key' => $entry['persona_key'],
        ]);

        DB::table('persona_versions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'persona_id' => $personaId,
            'version' => 1,
            'display_name' => $personaDefinition['display_name'],
            'disclosure' => $personaDefinition['disclosure'],
            'style_code' => $personaDefinition['style_code'],
            'definition_hash' => $personaHash,
            'review_status' => 'synthetic_unreviewed',
            'created_at' => $now,
        ]);

        $scenarioDefinition = [
            'topic_code' => $entry['topic_code'],
            'response_code' => $entry['response_code'],
        ];
        $scenarioHash = CanonicalJson::sha256($scenarioDefinition);

        DB::table('roleplay_scenarios')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'key' => $entry['scenario_key'],
            'mode' => 'roleplay',
            'status' => 'development_only',
            'created_by_attribution' => $attribution,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scenarioId = $this->requiredId('roleplay_scenarios', [
            'organization_id' => $organizationId,
            'key' => $entry['scenario_key'],
        ]);

        DB::table('scenario_versions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'scenario_id' => $scenarioId,
            'version' => 1,
            'topic_code' => $scenarioDefinition['topic_code'],
            'response_code' => $scenarioDefinition['response_code'],
            'definition_hash' => $scenarioHash,
            'review_status' => 'synthetic_unreviewed',
            'created_at' => $now,
        ]);

        $scenarioVersionId = $this->requiredId('scenario_versions', [
            'organization_id' => $organizationId,
            'scenario_id' => $scenarioId,
            'version' => 1,
        ]);

        foreach ($entry['objectives'] as $objective) {
            DB::table('scenario_objectives')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'scenario_version_id' => $scenarioVersionId,
                'objective_key' => $objective['objective_key'],
                'title' => $objective['title'],
                'completion_criteria_code' => $objective['completion_criteria_code'],
                'sort_order' => $objective['sort_order'],
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, int|string>  $where
     */
    private function requiredId(string $table, array $where): string
    {
        $id = DB::table($table)->where($where)->value('id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException(
                sprintf('Missing seeded %s row.', $table),
            );
        }

        return $id;
    }
}
