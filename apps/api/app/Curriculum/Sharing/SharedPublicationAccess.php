<?php

declare(strict_types=1);

namespace App\Curriculum\Sharing;

use App\Curriculum\Publication\VerifiedPublication;
use Carbon\CarbonInterface;

final readonly class SharedPublicationAccess
{
    public function __construct(
        public string $shareGrantId,
        public string $recipientOrganizationId,
        public string $use,
        public VerifiedPublication $publication,
        public CarbonInterface $evaluatedAt,
    ) {}
}
