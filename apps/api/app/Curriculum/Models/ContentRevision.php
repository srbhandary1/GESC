<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class ContentRevision extends OrganizationOwnedCurriculumModel
{
    public const string LOCALIZATION_KANNADA_REQUIRED = 'kn_required';

    public const string LOCALIZATION_ENGLISH_ONLY = 'english_only_pedagogical';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'learner_visible' => 'boolean',
            'payload' => 'array',
            'pedagogically_authoritative' => 'boolean',
            'authored_at' => 'immutable_datetime',
        ];
    }
}
