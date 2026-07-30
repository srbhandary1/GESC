<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class CurriculumShareGrant extends OrganizationOwnedCurriculumModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_preview' => 'boolean',
            'allow_enrollment' => 'boolean',
            'allow_clone' => 'boolean',
            'effective_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
        ];
    }
}
