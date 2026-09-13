<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use RuntimeException;

final class SpeechEconomicsEvidenceRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Speech economics evidence was rejected.');
    }
}
