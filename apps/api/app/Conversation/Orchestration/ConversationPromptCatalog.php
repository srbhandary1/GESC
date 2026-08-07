<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

use App\Conversation\Exceptions\ConversationOperationRejected;
use Illuminate\Support\Facades\DB;
use stdClass;

final class ConversationPromptCatalog
{
    /**
     * @return array{
     *   id: string,
     *   prompt_key: string,
     *   version: int,
     *   coaching_mode: string,
     *   body_sha256: string,
     *   body: string
     * }
     */
    public function active(string $coachingMode): array
    {
        $row = DB::table('prompt_versions')
            ->where('prompt_key', 'friend.turn.v1')
            ->where('coaching_mode', $coachingMode)
            ->where('status', 'active')
            ->where('locale', 'en-IN')
            ->first([
                'id',
                'prompt_key',
                'version',
                'coaching_mode',
                'body_sha256',
                'body',
            ]);

        if (! $row instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        $id = $row->id ?? null;
        $promptKey = $row->prompt_key ?? null;
        $version = $row->version ?? null;
        $mode = $row->coaching_mode ?? null;
        $hash = $row->body_sha256 ?? null;
        $body = $row->body ?? null;

        if (
            ! is_string($id)
            || ! is_string($promptKey)
            || (! is_int($version) && ! is_numeric($version))
            || ! is_string($mode)
            || ! is_string($hash)
            || ! is_string($body)
            || $body === ''
            || ! hash_equals(hash('sha256', $body), $hash)
        ) {
            throw new ConversationOperationRejected;
        }

        return [
            'id' => $id,
            'prompt_key' => $promptKey,
            'version' => (int) $version,
            'coaching_mode' => $mode,
            'body_sha256' => $hash,
            'body' => $body,
        ];
    }
}
