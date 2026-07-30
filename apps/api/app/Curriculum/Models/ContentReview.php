<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class ContentReview extends OrganizationOwnedCurriculumModel
{
    public const string KIND_ACADEMIC_ENGLISH = 'academic_en';

    public const string KIND_KANNADA = 'kannada';

    public const string DECISION_APPROVED = 'approved';

    public const string DECISION_REJECTED = 'rejected';

    public const string DECISION_WITHDRAWN = 'withdrawn';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime'];
    }
}
