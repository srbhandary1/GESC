<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace;

final readonly class ContentWorkspaceCommandClaim
{
    public function __construct(
        public string $id,
        public bool $replayed,
        public ?string $resultType = null,
        public ?string $resultId = null,
    ) {}
}
