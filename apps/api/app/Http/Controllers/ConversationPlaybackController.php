<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Metering\ConversationPlaybackMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConversationPlaybackController extends Controller
{
    public function __invoke(Request $request, ConversationPlaybackMeter $meter): JsonResponse
    {
        $data = $request->validate([
            'grant' => ['required', 'string', 'max:4096'],
            'playback_key' => ['required', 'string', 'regex:/\A[a-zA-Z0-9_-]{16,64}\z/'],
            'played_ms' => ['required', 'integer', 'min:0', 'max:120000'],
        ]);
        try {
            $result = $meter->progress(AuthenticatedPrincipal::fromRequest($request)->user, $data['grant'], $data['playback_key'], (int) $data['played_ms']);
        } catch (ConversationOperationRejected) {
            abort(404);
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
}
