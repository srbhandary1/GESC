<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Provisional delayed-review intervals
    |--------------------------------------------------------------------------
    |
    | These defaults require named academic review before production use.
    | Domain services must read this configuration and must not allow an AI
    | response to alter the order or timing of review occurrences.
    |
    */
    'interval_days' => [1, 3, 7, 14],
    'local_due_hour' => 9,
    'version' => 'provisional-v1',
];
