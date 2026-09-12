<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidateQuery;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionReconciliationCommands;
use App\Http\Requests\Operator\MediaLibrary\MediaTranscriptionReconciliationRequest;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\MediaLibraryCommandResult;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorMediaTranscriptionController extends Controller
{
    public function __construct(
        private readonly MediaTranscriptionCandidateQuery $queries,
        private readonly MediaTranscriptionReconciliationCommands $commands,
    ) {}

    public function candidate(
        Request $request,
        string $campaign,
        string $campaignAsset,
    ): JsonResponse {
        abort_if($request->query() !== [], 404);

        return response()->json($this->queries->get(
            $this->actor($request),
            $campaign,
            $campaignAsset,
        ))->header('Cache-Control', 'private, no-store');
    }

    public function reconcile(
        MediaTranscriptionReconciliationRequest $request,
        string $campaign,
        string $campaignAsset,
    ): JsonResponse {
        try {
            $result = $this->commands->reconcile(
                actor: $this->actor($request),
                campaignId: $campaign,
                campaignAssetId: $campaignAsset,
                expectedAssetVersion: (int) $request->validated('expected_asset_version'),
                candidateSha256: (string) $request->validated('candidate_sha256'),
                outcome: (string) $request->validated('outcome'),
                operatorLabel: $this->nullableString($request, 'operator_label'),
                correctedTranscript: $this->nullableString(
                    $request,
                    'corrected_transcript',
                ),
                expectedHeadRevisionId: $this->nullableString(
                    $request,
                    'expected_head_revision_id',
                ),
                expectedMetadataVersion: (int) $request->validated(
                    'expected_metadata_version',
                ),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (QueryException $exception) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::CONCURRENT_CHANGE,
                $exception,
            );
        }

        return $this->result($result);
    }

    private function result(MediaLibraryCommandResult $result): JsonResponse
    {
        return response()->json([
            'result_type' => $result->resultType,
            'result_id' => $result->resultId,
            'replayed' => $result->replayed,
            ...$result->data,
        ], $result->replayed ? Response::HTTP_OK : Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    private function actor(Request $request): User
    {
        return AuthenticatedPrincipal::fromRequest($request)->user;
    }

    private function nullableString(
        MediaTranscriptionReconciliationRequest $request,
        string $key,
    ): ?string {
        $value = $request->validated($key);

        return is_string($value) ? $value : null;
    }
}
