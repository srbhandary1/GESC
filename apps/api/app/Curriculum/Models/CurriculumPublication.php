<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class CurriculumPublication extends OrganizationOwnedCurriculumModel
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
