<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

final readonly class PublicationSnapshot
{
    public function __construct(
        public string $publicationId,
        public string $manifestHash,
    ) {}
}
