<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Operator\SearchOperatorShellRequest;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\Exceptions\OperatorShellRejected;
use App\Operator\OperatorOverviewQuery;
use App\Operator\OperatorSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class OperatorShellController extends Controller
{
    public function __construct(
        private readonly OperatorOverviewQuery $overview,
        private readonly OperatorSearchQuery $search,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->overview->show($principal->user);
        } catch (OperatorShellRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function search(SearchOperatorShellRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->search->search(
                actor: $principal->user,
                term: (string) $request->validated('q'),
                limit: (int) ($request->validated('limit') ?? 20),
                cursor: $this->nullableString(
                    $request->validated('cursor'),
                ),
            );
        } catch (OperatorShellRejected) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (InvalidOperatorCursor) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid or expired.'],
            ]);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
