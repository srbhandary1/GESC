<?php

declare(strict_types=1);

namespace App\Conversation\Evaluation;

use App\Conversation\Orchestration\ConversationOutputValidator;
use App\Conversation\Orchestration\ConversationPromptCatalog;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Curriculum\CanonicalJson;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final readonly class AiEvaluationRunner
{
    public function __construct(
        private AiEvaluationSuite $suite,
        private ConversationPromptCatalog $prompts,
        private ConversationModelProvider $model,
        private ConversationOutputValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        ?string $exportReviewPath = null,
        ?string $importReviewPath = null,
        ?string $reportPath = null,
    ): array {
        if (
            (! app()->environment('local') && ! app()->environment('testing'))
            || config('conversation.provider_driver') !== 'fake'
        ) {
            throw new \App\Conversation\Exceptions\ConversationOperationRejected;
        }

        $suite = $this->suite->load();
        $startedAt = Date::now()->toImmutable();
        $comparisons = [];
        $caseResults = [];
        $passed = 0;
        $failed = 0;
        $estimatedCost = 0;
        $humanReview = $this->loadHumanReview($importReviewPath);

        foreach ($suite['cases'] as $case) {
            $prompt = $this->prompts->active($case['coaching_mode']);
            $result = $this->model->respond(new ConversationModelRequest(
                interactionId: (string) Str::ulid(),
                transcript: $case['transcript'],
                personaStyleCode: 'patient-friend-v1',
                scenarioResponseCode: 'friend.everyday.follow-up.v1',
                coachingMode: $case['coaching_mode'],
                promptVersionId: $prompt['id'],
                promptBodySha256: $prompt['body_sha256'],
            ));

            if (! $this->validator->validate($result)) {
                $result = $this->fallback($result);
            }

            $observedCategory = $result->priorityCorrection?->errorCategory;
            $categoryMatch = $observedCategory === $case['expected_error_category'];
            $safetyMatch = $result->safetyState === $case['expected_safety_state'];
            $schemaValid = $this->validator->validate($result);
            $humanDisposition = $humanReview[$case['id']] ?? null;
            $humanPass = $humanDisposition === null
                || $humanDisposition === 'accept';
            $casePassed = $categoryMatch && $safetyMatch && $schemaValid && $humanPass;
            $passed += $casePassed ? 1 : 0;
            $failed += $casePassed ? 0 : 1;
            $estimatedCost += $result->usage()->estimatedCostMicrounits;
            $caseResults[] = [
                'id' => $case['id'],
                'coaching_mode' => $case['coaching_mode'],
                'prompt_version_id' => $prompt['id'],
                'prompt_body_sha256' => $prompt['body_sha256'],
                'expected_error_category' => $case['expected_error_category'],
                'observed_error_category' => $observedCategory,
                'expected_safety_state' => $case['expected_safety_state'],
                'observed_safety_state' => $result->safetyState,
                'schema_valid' => $schemaValid,
                'human_disposition' => $humanDisposition,
                'passed' => $casePassed,
                'estimated_cost_microunits' => $result->usage()
                    ->estimatedCostMicrounits,
            ];
            $comparisons[$prompt['body_sha256']][] = $case['id'];
        }

        $completedAt = Date::now()->toImmutable();
        $report = [
            'suite_key' => $suite['suite_key'],
            'suite_version' => $suite['suite_version'],
            'suite_hash' => $suite['suite_hash'],
            'status' => $failed === 0 ? 'completed' : 'failed',
            'case_count' => count($caseResults),
            'passed_count' => $passed,
            'failed_count' => $failed,
            'estimated_cost_microunits' => $estimatedCost,
            'prompt_comparison' => $this->comparePrompts($caseResults),
            'cost_ledger_rollup' => [
                'provider_key' => 'deterministic-fake',
                'estimated_cost_microunits' => $estimatedCost,
                'case_count' => count($caseResults),
            ],
            'cases' => $caseResults,
            'started_at' => $startedAt->toIso8601String(),
            'completed_at' => $completedAt->toIso8601String(),
        ];
        $reportDigest = CanonicalJson::sha256([
            'suite_hash' => $report['suite_hash'],
            'status' => $report['status'],
            'passed_count' => $report['passed_count'],
            'failed_count' => $report['failed_count'],
            'estimated_cost_microunits' => $report['estimated_cost_microunits'],
            'cases' => array_map(
                static fn (array $case): array => [
                    'id' => $case['id'],
                    'passed' => $case['passed'],
                    'observed_error_category' => $case['observed_error_category'],
                    'observed_safety_state' => $case['observed_safety_state'],
                ],
                $caseResults,
            ),
        ]);
        $report['report_digest'] = $reportDigest;

        DB::table('evaluation_runs')->insert([
            'id' => (string) Str::ulid(),
            'suite_key' => $suite['suite_key'],
            'suite_version' => $suite['suite_version'],
            'suite_hash' => $suite['suite_hash'],
            'status' => $report['status'],
            'case_count' => $report['case_count'],
            'passed_count' => $passed,
            'failed_count' => $failed,
            'estimated_cost_microunits' => $estimatedCost,
            'report_digest' => $reportDigest,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        if (is_string($exportReviewPath) && $exportReviewPath !== '') {
            $this->exportReview($exportReviewPath, $suite, $caseResults);
        }

        if (is_string($reportPath) && $reportPath !== '') {
            File::ensureDirectoryExists(dirname($reportPath));
            File::put(
                $reportPath,
                json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n",
            );
        }

        return $report;
    }

    /**
     * @param  list<array<string, mixed>>  $caseResults
     * @return list<array<string, mixed>>
     */
    private function comparePrompts(array $caseResults): array
    {
        $byHash = [];

        foreach ($caseResults as $case) {
            $hash = (string) $case['prompt_body_sha256'];
            $byHash[$hash] ??= [
                'prompt_body_sha256' => $hash,
                'coaching_modes' => [],
                'case_count' => 0,
                'passed_count' => 0,
            ];
            $byHash[$hash]['case_count']++;
            $byHash[$hash]['passed_count'] += $case['passed'] ? 1 : 0;
            $mode = (string) $case['coaching_mode'];

            if (! in_array($mode, $byHash[$hash]['coaching_modes'], true)) {
                $byHash[$hash]['coaching_modes'][] = $mode;
            }
        }

        return array_values($byHash);
    }

    /**
     * @return array<string, string>
     */
    private function loadHumanReview(?string $path): array
    {
        if (! is_string($path) || $path === '' || ! File::isFile($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! is_array($decoded['dispositions'] ?? null)) {
            return [];
        }

        $map = [];

        foreach ($decoded['dispositions'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $caseId = $row['case_id'] ?? null;
            $disposition = $row['disposition'] ?? null;

            if (is_string($caseId) && is_string($disposition)) {
                $map[$caseId] = $disposition;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $suite
     * @param  list<array<string, mixed>>  $caseResults
     */
    private function exportReview(
        string $path,
        array $suite,
        array $caseResults,
    ): void {
        $payload = [
            'suite_key' => $suite['suite_key'],
            'suite_hash' => $suite['suite_hash'],
            'exported_at' => Date::now()->toIso8601String(),
            'dispositions' => array_map(
                static fn (array $case): array => [
                    'case_id' => $case['id'],
                    'prompt_body_sha256' => $case['prompt_body_sha256'],
                    'coaching_mode' => $case['coaching_mode'],
                    'observed_error_category' => $case['observed_error_category'],
                    'disposition' => 'pending',
                ],
                $caseResults,
            ),
        ];
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n",
        );
    }

    private function fallback(
        ConversationModelResult $result,
    ): ConversationModelResult {
        return new ConversationModelResult(
            understoodIntent: 'safe_fallback',
            assistantReply: 'Thank you. Let us continue with a simple next sentence.',
            providerUsage: $result->usage(),
            priorityCorrection: new \App\Conversation\Orchestration\PriorityCorrection(
                errorCategory: 'safety.fallback',
                correctionEn: 'Please try one short, clear English sentence.',
                explanationKn: "ದಯವಿಟ್ಟು ಒಂದು ಸಣ್ಣ, ಸ್ಪಷ್ಟ ಇಂಗ್ಲಿಷ್ ವಾಕ್ಯ ಹೇಳಿ.",
                retryRecommended: true,
            ),
            safetyState: 'fallback',
            usedFallback: true,
        );
    }
}
