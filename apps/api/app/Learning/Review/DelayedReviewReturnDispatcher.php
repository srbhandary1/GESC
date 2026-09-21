<?php

declare(strict_types=1);

namespace App\Learning\Review;

use Carbon\CarbonImmutable;

final class DelayedReviewReturnDispatcher
{
    /**
     * @return array{
     *   status: 'disabled'|'completed',
     *   considered: int,
     *   sent: int,
     *   skipped: int,
     *   skip_reason: string|null
     * }
     */
    public function dispatchDue(?CarbonImmutable $asOf = null): array
    {
        unset($asOf);

        $enabled = config('review.return_email.enabled') === true
            || config('review.return_email.enabled') === 'true'
            || config('review.return_email.enabled') === '1';
        $approval = config('review.return_email.approval');

        if (! $enabled || ! is_string($approval) || $approval === '') {
            return [
                'status' => 'disabled',
                'considered' => 0,
                'sent' => 0,
                'skipped' => 0,
                'skip_reason' => 'privacy_gate',
            ];
        }

        // Recovery contacts are stored as identifier digests. No plaintext
        // destination may be invented, and lesson/transcript/audio must never
        // enter the envelope. Sending stays skipped until a reviewed adapter
        // is bound by APP.
        return [
            'status' => 'completed',
            'considered' => 0,
            'sent' => 0,
            'skipped' => 0,
            'skip_reason' => 'destination_unavailable',
        ];
    }
}
