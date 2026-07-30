<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class AuthorityAppointment extends OrganizationOwnedCurriculumModel
{
    public const string ENGLISH_ACADEMIC_REVIEWER = 'english_academic_reviewer';

    public const string KANNADA_REVIEWER = 'kannada_reviewer';

    public const string RIGHTS_CONFIRMER = 'rights_confirmer';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'appointed_at' => 'immutable_datetime',
        ];
    }
}
