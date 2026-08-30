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
        bool $requireHumanReview = false,
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
        $humanReview = $this->loadHumanReview(
            $importReviewPath,
            $suite,
            $requireHumanReview,
        );

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
            $humanReviewRow = $humanReview[$case['id']] ?? null;
            $humanDisposition = $humanReviewRow['disposition'] ?? null;
            $reviewPayloadSha256 = $this->reviewPayloadSha256([
                'case_id' => $case['id'],
                'ui_locale' => $uiLocale,
                'prompt_body_sha256' => $prompt['body_sha256'],
                'coaching_mode' => $case['coaching_mode'],
                'observed_error_category' => $observedCategory,
                'assistant_reply_en' => $result->assistantReply,
                'correction_en' => $result->priorityCorrection?->correctionEn,
                'explanation_l1' => $result->priorityCorrection?->explanationL1,
                'explanation_locale' => $result->priorityCorrection?->explanationLocale,
                'safety_state' => $result->safetyState,
            ]);

            if (
                $requireHumanReview
                && ! hash_equals(
                    $reviewPayloadSha256,
                    (string) ($humanReviewRow['review_payload_sha256'] ?? ''),
                )
            ) {
                throw new ConversationOperationRejected;
            }

            $humanPass = $requireHumanReview
                ? $humanDisposition === 'accept'
                : $humanDisposition === null
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
                'assistant_reply_en' => $result->assistantReply,
                'correction_en' => $result->priorityCorrection?->correctionEn,
                'explanation_l1' => $result->priorityCorrection?->explanationL1,
                'schema_valid' => $schemaValid,
                'explanation_l1_present' => $l1Present,
                'explanation_locale' => $result->priorityCorrection?->explanationLocale,
                'false_correction' => ! $categoryMatch,
                'shame_rejected' => $shameRejected,
                'expects_shame_rejection' => (bool) $case['expects_shame_rejection'],
                'human_disposition' => $humanDisposition,
                'human_reviewer_id' => $humanReviewRow['reviewer_id'] ?? null,
                'human_review_evidence_reference' => $humanReviewRow['evidence_reference'] ?? null,
                'human_review_payload_sha256' => $humanReviewRow['review_payload_sha256'] ?? null,
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
            'human_review_required' => $requireHumanReview,
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
            'human_review_required' => $requireHumanReview,
            'metrics' => $metrics,
            'cases' => array_map(
                static fn (array $case): array => [
                    'id' => $case['id'],
                    'ui_locale' => $case['ui_locale'],
                    'passed' => $case['passed'],
                    'observed_error_category' => $case['observed_error_category'],
                    'observed_safety_state' => $case['observed_safety_state'],
                    'explanation_l1_present' => $case['explanation_l1_present'],
                    'human_disposition' => $case['human_disposition'],
                    'human_reviewer_id' => $case['human_reviewer_id'],
                    'human_review_evidence_reference' => $case['human_review_evidence_reference'],
                    'human_review_payload_sha256' => $case['human_review_payload_sha256'],
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
     * @return array<string, array{
     *   disposition: string,
     *   reviewer_id: ?string,
     *   evidence_reference: ?string,
     *   review_payload_sha256: ?string
     * }>
     */
    private function loadHumanReview(
        ?string $path,
        array $suite,
        bool $required,
    ): array {
        if (! is_string($path) || $path === '') {
            if ($required) {
                throw new ConversationOperationRejected;
            }

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
            $reviewerId = $row['reviewer_id'] ?? null;
            $reviewerRole = $row['reviewer_role'] ?? null;
            $reviewerLocale = $row['reviewer_locale'] ?? null;
            $independentReview = $row['independent_review'] ?? null;
            $reviewedAt = $row['reviewed_at'] ?? null;
            $evidenceReference = $row['evidence_reference'] ?? null;
            $reviewPayloadSha256 = $row['review_payload_sha256'] ?? null;
            $expectedReviewerRole = $reviewerLocale === 'en-IN'
                ? 'independent_academic_english_reviewer'
                : 'independent_language_content_reviewer';

            if (
                ! is_string($caseId)
                || $caseId === ''
                || ! isset($knownCaseIds[$caseId])
                || ! is_string($disposition)
                || ! in_array($disposition, ['pending', 'accept', 'reject'], true)
            ) {
                throw new ConversationOperationRejected;
            }

            if ($required && (
                $disposition !== 'accept'
                || ! is_string($reviewerId)
                || trim($reviewerId) === ''
                || ! is_string($reviewerRole)
                || $reviewerRole !== $expectedReviewerRole
                || ! is_string($reviewerLocale)
                || $reviewerLocale !== $this->caseLocale($suite, $caseId)
                || $independentReview !== true
                || ! is_string($reviewedAt)
                || preg_match(
                    '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/',
                    $reviewedAt,
                ) !== 1
                || strtotime($reviewedAt) === false
                || ! is_string($evidenceReference)
                || trim($evidenceReference) === ''
                || ! is_string($reviewPayloadSha256)
                || preg_match('/\A[0-9a-f]{64}\z/', $reviewPayloadSha256) !== 1
                || ! hash_equals(
                    $reviewPayloadSha256,
                    $this->reviewPayloadSha256([
                        'case_id' => $row['case_id'] ?? null,
                        'ui_locale' => $row['ui_locale'] ?? null,
                        'prompt_body_sha256' => $row['prompt_body_sha256'] ?? null,
                        'coaching_mode' => $row['coaching_mode'] ?? null,
                        'observed_error_category' => $row['observed_error_category'] ?? null,
                        'assistant_reply_en' => $row['assistant_reply_en'] ?? null,
                        'correction_en' => $row['correction_en'] ?? null,
                        'explanation_l1' => $row['explanation_l1'] ?? null,
                        'explanation_locale' => $row['explanation_locale'] ?? null,
                        'safety_state' => $row['safety_state'] ?? null,
                    ]),
                )
            )) {
                throw new ConversationOperationRejected;
            }

            if (isset($map[$caseId])) {
                throw new ConversationOperationRejected;
            }

            $map[$caseId] = [
                'disposition' => $disposition,
                'reviewer_id' => is_string($reviewerId) ? $reviewerId : null,
                'evidence_reference' => is_string($evidenceReference)
                    ? $evidenceReference
                    : null,
                'review_payload_sha256' => is_string($reviewPayloadSha256)
                    ? $reviewPayloadSha256
                    : null,
            ];
        }

        if ($required && count($map) !== count($knownCaseIds)) {
            throw new ConversationOperationRejected;
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
                fn (array $case): array => array_merge(
                    $reviewPayload = [
                        'case_id' => $case['id'],
                        'ui_locale' => $case['ui_locale'],
                        'prompt_body_sha256' => $case['prompt_body_sha256'],
                        'coaching_mode' => $case['coaching_mode'],
                        'observed_error_category' => $case['observed_error_category'],
                        'assistant_reply_en' => $case['assistant_reply_en'],
                        'correction_en' => $case['correction_en'],
                        'explanation_l1' => $case['explanation_l1'],
                        'explanation_locale' => $case['explanation_locale'],
                        'safety_state' => $case['observed_safety_state'],
                    ],
                    [
                        'review_payload_sha256' => $this->reviewPayloadSha256(
                            $reviewPayload,
                        ),
                        'disposition' => 'pending',
                        'reviewer_id' => null,
                        'reviewer_role' => null,
                        'reviewer_locale' => $case['ui_locale'],
                        'independent_review' => false,
                        'reviewed_at' => null,
                        'evidence_reference' => null,
                    ],
                ),
                $caseResults,
            ),
        ];
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n",
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reviewPayloadSha256(array $payload): string
    {
        return CanonicalJson::sha256($payload);
    }

    /**
     * @param  array<string, mixed>  $suite
     */
    private function caseLocale(array $suite, string $caseId): ?string
    {
        foreach ($suite['cases'] as $case) {
            if (($case['id'] ?? null) === $caseId) {
                $locale = $case['ui_locale'] ?? null;

                return is_string($locale) ? $locale : null;
            }
        }

        return null;
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
