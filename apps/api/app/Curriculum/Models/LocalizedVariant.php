<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class LocalizedVariant extends OrganizationOwnedCurriculumModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'translated_at' => 'immutable_datetime',
        ];
    }
}
