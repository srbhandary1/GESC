<?php

declare(strict_types=1);

namespace App\Learning\Home;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class MeaningfulSpeakingDayProjector
{
    public function __construct(
        private MeaningfulSpeakingDayPredicate $predicate,
    ) {}

    public function reconcileTurn(
        string $organizationId,
        string $membershipId,
        string $turnId,
    ): void {
        $turn = DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $turnId)
            ->first([
                'id',
                'completed_at',
                'assistant_reply_digest',
                'failed_stage',
            ]);

        if (! $turn instanceof stdClass || ! $this->predicate->qualifies($turn)) {
            return;
        }

        $timezone = DB::table('organizations')
            ->where('id', $organizationId)
            ->value('timezone');
        $timezone = is_string($timezone) && $timezone !== ''
            ? $timezone
            : 'Asia/Kolkata';
        $completedAt = CarbonImmutable::parse((string) $turn->completed_at);

        DB::table('meaningful_speaking_days')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'learner_membership_id' => $membershipId,
            'speaking_date' => $completedAt->setTimezone($timezone)->toDateString(),
            'timezone' => $timezone,
            'source_turn_id' => $turnId,
            'created_at' => now(),
        ]);
    }

    public function reconcileLearner(
        string $organizationId,
        string $membershipId,
    ): void {
        DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->whereNotNull('completed_at')
            ->whereNotNull('assistant_reply_digest')
            ->whereNull('failed_stage')
            ->orderBy('completed_at')
            ->pluck('id')
            ->each(fn (string $id) => $this->reconcileTurn(
                $organizationId,
                $membershipId,
                $id,
            ));
    }
}
