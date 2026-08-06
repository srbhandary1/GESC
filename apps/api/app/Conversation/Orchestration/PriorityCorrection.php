<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

final readonly class PriorityCorrection
{
    public function __construct(
        public string $errorCategory,
        public string $correctionEn,
        public string $explanationKn,
        public bool $retryRecommended,
    ) {}

    /**
     * @return array{
     *   error_category: string,
     *   correction_en: string,
     *   explanation_kn: string,
     *   retry_recommended: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'error_category' => $this->errorCategory,
            'correction_en' => $this->correctionEn,
            'explanation_kn' => $this->explanationKn,
            'retry_recommended' => $this->retryRecommended,
        ];
    }
}
