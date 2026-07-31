<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Conversation\Voice\VoiceRecordingService;
use App\Http\Requests\Voice\GrantVoiceConsentRequest;
use App\Http\Requests\Voice\ListVoiceConsentsRequest;
use App\Http\Requests\Voice\ReviewVoiceTranscriptRequest;
use App\Http\Requests\Voice\UploadVoiceRecordingRequest;
use App\Http\Requests\Voice\WithdrawVoiceConsentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VoiceController extends Controller
{
    public function __construct(
        private readonly VoiceConsentService $consents,
        private readonly VoiceRecordingService $recordings,
    ) {}

    public function consents(ListVoiceConsentsRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->consents->listing(
                $principal->user,
                (string) $request->validated('locale', 'en-IN'),
            );
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function grant(GrantVoiceConsentRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->consents->grant(
                learner: $principal->user,
                purpose: (string) $request->validated('purpose'),
                requestedLocale: (string) $request->validated(
                    'requested_locale',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (ConversationIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $result,
            $result['replayed'] === true
                ? Response::HTTP_OK
                : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function withdraw(
        WithdrawVoiceConsentRequest $request,
        string $purpose,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->consents->withdraw(
                learner: $principal->user,
                purpose: $purpose,
                requestedLocale: (string) $request->validated(
                    'requested_locale',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
            if (
                $purpose === VoiceConsentService::UPLOAD
                || $purpose === VoiceConsentService::PROVIDER
            ) {
                $this->recordings->deleteForWithdrawal($principal->user);
            }
        } catch (ConversationIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function upload(
        UploadVoiceRecordingRequest $request,
        string $session,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $file = $request->file('audio');

        if ($file === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bytes = $file->get();

        if (! is_string($bytes)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->recordings->create(
                learner: $principal->user,
                sessionId: $session,
                audioBytes: $bytes,
                declaredMimeType: (string) $file->getMimeType(),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (ConversationIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(
            $result,
            $result['replayed'] === true
                ? Response::HTTP_OK
                : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $recording): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->recordings->show(
                $principal->user,
                $recording,
            );
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function review(
        ReviewVoiceTranscriptRequest $request,
        string $recording,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->recordings->review(
                learner: $principal->user,
                recordingId: $recording,
                action: (string) $request->validated('action'),
                correctedTranscript: $request->validated(
                    'corrected_transcript',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (ConversationIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function delete(Request $request, string $recording): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->recordings->delete(
                $principal->user,
                $recording,
            );
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function deletion(
        Request $request,
        string $recording,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->recordings->deletion(
                $principal->user,
                $recording,
            );
        } catch (VoiceOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }
}
