<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

final readonly class MediaLibraryCommandResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $resultType,
        public string $resultId,
        public bool $replayed,
        public array $data = [],
    ) {}
}
