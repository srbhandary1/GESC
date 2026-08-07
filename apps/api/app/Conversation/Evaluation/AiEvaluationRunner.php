<?php

declare(strict_types=1);

namespace App\Conversation\Evaluation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Orchestration\ConversationOutputValidator;
use App\Conversation\Orchestration\ConversationPromptCatalog;
use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Support\ConversationUiLocales;
use App\Conversation\Support\SyntheticL1Explanations;
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
            throw new ConversationOperationRejected;
        }

        $suite = $this->suite->load();
        $startedAt = Date::now()->toImmutable();
        $caseResults = [];
        $passed = 0;
        $failed = 0;
        $estimatedCost = 0;
        $falseCorrectionCount = 0;
        $missingL1Count = 0;
        $shameLanguageHits = 0;
        $schemaInvalidCount = 0;
        $humanReview = $this->loadHumanReview($importReviewPath, $suite);

        foreach ($suite['cases'] as $case) {
            $uiLocale = (string) $case['ui_locale'];
            $prompt = $this->prompts->active($case['coaching_mode']);
            $rawResult = $this->model->respond(new ConversationModelRequest(
                interactionId: (string) Str::ulid(),
                transcript: $case['transcript'],
                personaStyleCode: 'patient-friend-v1',
                scenarioResponseCode: 'friend.everyday.follow-up.v1',
                coachingMode: $case['coaching_mode'],
                uiLocale: $uiLocale,
                promptVersionId: $prompt['id'],
                promptBodySha256: $prompt['body_sha256'],
                promptBody: $prompt['body'],
            ));

            $rawSchemaValid = $this->validator->validate($rawResult);
            $shameRejected = ! $rawSchemaValid && (bool) $case['expects_shame_rejection'];
            $result = $rawSchemaValid
                ? $rawResult
                : $this->fallback($rawResult, $uiLocale);

            $observedCategory = $result->priorityCorrection?->errorCategory;
            $categoryMatch = $observedCategory === $case['expected_error_category'];
            $safetyMatch = $result->safetyState === $case['expected_safety_state'];
            $schemaValid = $this->validator->validate($result);
            $l1Present = $this->explanationL1Adequate(
                $result,
                $uiLocale,
                (bool) $case['requires_explanation_l1'],
            );
            $humanDisposition = $humanReview[$case['id']] ?? null;
            $humanPass = $humanDisposition === null
                || $humanDisposition === 'accept'
                || $humanDisposition === 'pending';
            $shamePass = ! (bool) $case['expects_shame_rejection'] || (
                $shameRejected
                && $result->usedFallback
                && $result->safetyState === 'fallback'
            );

            if (! $categoryMatch) {
                $falseCorrectionCount++;
            }
            if ((bool) $case['requires_explanation_l1'] && ! $l1Present) {
                $missingL1Count++;
            }
            if ((bool) $case['expects_shame_rejection'] && $shameRejected) {
                $shameLanguageHits++;
            }
            if (! $schemaValid) {
                $schemaInvalidCount++;
            }

            $casePassed = $categoryMatch
                && $safetyMatch
                && $schemaValid
                && $l1Present
                && $shamePass
                && $humanPass;
            $passed += $casePassed ? 1 : 0;
            $failed += $casePassed ? 0 : 1;
            $estimatedCost += $result->usage()->estimatedCostMicrounits;
            $caseResults[] = [
                'id' => $case['id'],
                'coaching_mode' => $case['coaching_mode'],
                'ui_locale' => $uiLocale,
                'prompt_version_id' => $prompt['id'],
                'prompt_body_sha256' => $prompt['body_sha256'],
                'expected_error_category' => $case['expected_error_category'],
                'observed_error_category' => $observedCategory,
                'expected_safety_state' => $case['expected_safety_state'],
                'observed_safety_state' => $result->safetyState,
                'schema_valid' => $schemaValid,
                'explanation_l1_present' => $l1Present,
                'explanation_locale' => $result->priorityCorrection?->explanationLocale,
                'false_correction' => ! $categoryMatch,
                'shame_rejected' => $shameRejected,
                'expects_shame_rejection' => (bool) $case['expects_shame_rejection'],
                'human_disposition' => $humanDisposition,
                'passed' => $casePassed,
                'estimated_cost_microunits' => $result->usage()
                    ->estimatedCostMicrounits,
            ];
        }

        $costCeilingOk = $estimatedCost === 0;
        $expectedShameCount = $this->expectedShameCaseCount($suite['cases']);
        $metrics = [
            'false_correction_count' => $falseCorrectionCount,
            'missing_l1_count' => $missingL1Count,
            'shame_language_hits' => $shameLanguageHits,
            'schema_invalid_count' => $schemaInvalidCount,
            'cost_ceiling_ok' => $costCeilingOk,
            'ui_locales_covered' => $suite['ui_locales'],
        ];
        $metricsPass = $falseCorrectionCount === 0
            && $missingL1Count === 0
            && $schemaInvalidCount === 0
            && $costCeilingOk
            && $shameLanguageHits === $expectedShameCount;

        $completedAt = Date::now()->toImmutable();
        $status = ($failed === 0 && $metricsPass) ? 'completed' : 'failed';
        $failedCount = $status === 'completed' ? 0 : max($failed, 1);

        $report = [
            'suite_key' => $suite['suite_key'],
            'suite_version' => $suite['suite_version'],
            'suite_hash' => $suite['suite_hash'],
            'status' => $status,
            'case_count' => count($caseResults),
            'passed_count' => $passed,
            'failed_count' => $failedCount,
            'estimated_cost_microunits' => $estimatedCost,
            'metrics' => $metrics,
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
            'metrics' => $metrics,
            'cases' => array_map(
                static fn (array $case): array => [
                    'id' => $case['id'],
                    'ui_locale' => $case['ui_locale'],
                    'passed' => $case['passed'],
                    'observed_error_category' => $case['observed_error_category'],
                    'observed_safety_state' => $case['observed_safety_state'],
                    'explanation_l1_present' => $case['explanation_l1_present'],
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
            'failed_count' => $failedCount,
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
     * @param  array<string, mixed>  $suite
     * @return array<string, string>
     */
    private function loadHumanReview(?string $path, array $suite): array
    {
        if (! is_string($path) || $path === '') {
            return [];
        }

        if (! File::isFile($path)) {
            throw new ConversationOperationRejected;
        }

        try {
            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ConversationOperationRejected;
        }

        if (
            ! is_array($decoded)
            || ($decoded['suite_key'] ?? null) !== $suite['suite_key']
            || ($decoded['suite_hash'] ?? null) !== $suite['suite_hash']
            || ! is_array($decoded['dispositions'] ?? null)
        ) {
            throw new ConversationOperationRejected;
        }

        $knownCaseIds = [];

        foreach ($suite['cases'] as $case) {
            $knownCaseIds[(string) $case['id']] = true;
        }

        $map = [];

        foreach ($decoded['dispositions'] as $row) {
            if (! is_array($row)) {
                throw new ConversationOperationRejected;
            }

            $caseId = $row['case_id'] ?? null;
            $disposition = $row['disposition'] ?? null;

            if (
                ! is_string($caseId)
                || $caseId === ''
                || ! isset($knownCaseIds[$caseId])
                || ! is_string($disposition)
                || ! in_array($disposition, ['pending', 'accept', 'reject'], true)
            ) {
                throw new ConversationOperationRejected;
            }

            if (isset($map[$caseId])) {
                throw new ConversationOperationRejected;
            }

            $map[$caseId] = $disposition;
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
                    'ui_locale' => $case['ui_locale'],
                    'prompt_body_sha256' => $case['prompt_body_sha256'],
                    'coaching_mode' => $case['coaching_mode'],
                    'observed_error_category' => $case['observed_error_category'],
                    'explanation_locale' => $case['explanation_locale'],
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

    private function explanationL1Adequate(
        ConversationModelResult $result,
        string $uiLocale,
        bool $required,
    ): bool {
        if (! $required) {
            return true;
        }

        $correction = $result->priorityCorrection;
        if ($correction === null || $correction->needed === false) {
            return false;
        }

        return trim($correction->explanationL1) !== ''
            && $correction->explanationLocale === $uiLocale
            && ConversationUiLocales::isSupported($correction->explanationLocale);
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     */
    private function expectedShameCaseCount(array $cases): int
    {
        $count = 0;

        foreach ($cases as $case) {
            if ((bool) ($case['expects_shame_rejection'] ?? false)) {
                $count++;
            }
        }

        return $count;
    }

    private function fallback(
        ConversationModelResult $result,
        string $uiLocale,
    ): ConversationModelResult {
        $locale = ConversationUiLocales::isSupported($uiLocale)
            ? $uiLocale
            : ConversationUiLocales::default();

        return new ConversationModelResult(
            understoodIntent: 'safe_fallback',
            assistantReply: 'Thank you. Let us continue with a simple next sentence.',
            providerUsage: $result->usage(),
            priorityCorrection: new PriorityCorrection(
                errorCategory: 'safety.fallback',
                correctionEn: 'Please try one short, clear English sentence.',
                explanationL1: SyntheticL1Explanations::for(
                    SyntheticL1Explanations::safetyFallback(),
                    $locale,
                ),
                explanationLocale: $locale,
                retryRecommended: true,
            ),
            safetyState: 'fallback',
            usedFallback: true,
        );
    }
}
