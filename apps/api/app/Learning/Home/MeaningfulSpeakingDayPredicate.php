<?php

declare(strict_types=1);

namespace App\Learning\Home;

use stdClass;

final class MeaningfulSpeakingDayPredicate
{
    public function qualifies(stdClass $turn): bool
    {
        return $turn->completed_at !== null
            && is_string($turn->assistant_reply_digest)
            && $turn->assistant_reply_digest !== ''
            && $turn->failed_stage === null;
    }
}
