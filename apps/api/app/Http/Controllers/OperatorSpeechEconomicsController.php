<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Conversation\Observability\SpeechEconomicsEvidenceRejected;
use App\Http\Requests\Operator\ListSpeechEconomicsRequest;
use App\Http\Requests\Operator\RecordSpeechProviderStatementRequest;
use App\Http\Requests\Operator\RecordSpeechRateCardRequest;
use App\Operator\Exceptions\SpeechEconomicsRejected;
use App\Operator\SpeechEconomicsCommercialCommands;
use App\Operator\SpeechEconomicsCommercialQuery;
use App\Operator\SpeechEconomicsExportService;
use App\Operator\SpeechEconomicsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperatorSpeechEconomicsController extends Controller
{
    public function __construct(
        private readonly SpeechEconomicsQuery $usage,
        private readonly SpeechEconomicsExportService $exports,
        private readonly SpeechEconomicsCommercialQuery $commercial,
        private readonly SpeechEconomicsCommercialCommands $commands,
    ) {}

    public function show(ListSpeechEconomicsRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->usage->summarize(
                $principal->user,
                $this->filters($request),
            );
        } catch (SpeechEconomicsRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(ListSpeechEconomicsRequest $request): StreamedResponse|Response
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->exports->export(
                $principal->user,
                $this->filters($request),
            );
        } catch (SpeechEconomicsRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['csv'];
            },
            'speech-economics-'.$result['export_id'].'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'X-Export-Id' => $result['export_id'],
            ],
        );
    }

    public function commercial(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->commercial->show($principal->user);
        } catch (SpeechEconomicsRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function recordRateCard(RecordSpeechRateCardRequest $request): JsonResponse
    {
        return $this->commercialCommand($request, fn (AuthenticatedPrincipal $principal): array => $this->commands->recordRateCard($principal->user, $request->validated()));
    }

    public function recordStatement(RecordSpeechProviderStatementRequest $request): JsonResponse
    {
        return $this->commercialCommand($request, fn (AuthenticatedPrincipal $principal): array => $this->commands->recordStatement($principal->user, $request->validated()));
    }

    /** @param callable(AuthenticatedPrincipal): array<string, mixed> $command */
    private function commercialCommand(Request $request, callable $command): JsonResponse
    {
        try {
            $result = $command(AuthenticatedPrincipal::fromRequest($request));
        } catch (SpeechEconomicsRejected) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (SpeechEconomicsEvidenceRejected) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($result, ($result['replayed'] ?? false) ? Response::HTTP_OK : Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array<string, string>
     */
    private function filters(ListSpeechEconomicsRequest $request): array
    {
        $filters = [];
        foreach ($request->validated() as $key => $value) {
            if (is_string($value)) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
