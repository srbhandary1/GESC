<?php

declare(strict_types=1);

namespace App\Curriculum\Authoring;

final readonly class ContentClassification
{
    public function __construct(
        public string $contentType,
        public bool $learnerVisible,
        public string $localizationRequirement,
        public bool $pedagogicallyAuthoritative,
    ) {}
}
