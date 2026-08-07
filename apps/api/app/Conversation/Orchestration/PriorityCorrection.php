<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

/**
 * Logically separate priority correction. Never merged into the Friend reply.
 *
 * explanation_l1 is the active UI-locale explanation. explanation_kn remains a
 * deprecated dual-write alias when explanation_locale is kn-IN (APP-0021 A2).
 */
final readonly class PriorityCorrection
{
    public function __construct(
        public string $errorCategory,
        public string $correctionEn,
        public string $explanationL1,
        public string $explanationLocale,
        public bool $retryRecommended,
        public bool $needed = true,
        public ?string $learnerSpan = null,
        public ?string $encouragementL1 = null,
    ) {}

    /**
     * @return array{
     *   needed: bool,
     *   error_category: string,
     *   correction_en: string,
     *   explanation_l1: string,
     *   explanation_locale: string,
     *   explanation_kn?: string,
     *   retry_recommended: bool,
     *   learner_span?: string,
     *   encouragement_l1?: string
     * }
     */
    public function toArray(): array
    {
        $payload = [
            'needed' => $this->needed,
            'error_category' => $this->errorCategory,
            'correction_en' => $this->correctionEn,
            'explanation_l1' => $this->explanationL1,
            'explanation_locale' => $this->explanationLocale,
            'retry_recommended' => $this->retryRecommended,
        ];

        if ($this->explanationLocale === 'kn-IN') {
            $payload['explanation_kn'] = $this->explanationL1;
        }

        if ($this->learnerSpan !== null && $this->learnerSpan !== '') {
            $payload['learner_span'] = $this->learnerSpan;
        }

        if (
            $this->encouragementL1 !== null
            && $this->encouragementL1 !== ''
        ) {
            $payload['encouragement_l1'] = $this->encouragementL1;
        }

        return $payload;
    }
}
