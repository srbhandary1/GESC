<?php

declare(strict_types=1);

namespace App\Curriculum\Authoring;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\GovernanceInput;

final class ContentClassificationPolicy
{
    /**
     * PR-005 has no non-pedagogical content record type. Callers therefore
     * cannot downgrade academic-review coverage with a boolean flag.
     *
     * @var list<string>
     */
    private const array CONTENT_TYPES = [
        'assessment_rationale',
        'dialogue',
        'explanation',
        'feedback',
        'instruction',
        'learner_action',
        'model_text',
        'safety_note',
        'title',
        'transcript',
    ];

    /**
     * English-only content remains exceptional and needs an academic-review
     * rationale at publication. These learner-guidance types may never use it.
     *
     * @var list<string>
     */
    private const array KANNADA_REQUIRED_TYPES = [
        'explanation',
        'feedback',
        'instruction',
        'learner_action',
    ];

    public function classify(
        string $contentType,
        bool $learnerVisible,
        string $localizationRequirement,
    ): ContentClassification {
        $contentType = GovernanceInput::key($contentType, 48);

        if (
            ! in_array($contentType, self::CONTENT_TYPES, true)
            || ! in_array(
                $localizationRequirement,
                ['kn_required', 'english_only_pedagogical'],
                true,
            )
            || (
                $localizationRequirement === 'english_only_pedagogical'
                && in_array(
                    $contentType,
                    self::KANNADA_REQUIRED_TYPES,
                    true,
                )
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        return new ContentClassification(
            contentType: $contentType,
            learnerVisible: $learnerVisible,
            localizationRequirement: $localizationRequirement,
            pedagogicallyAuthoritative: true,
        );
    }
}
