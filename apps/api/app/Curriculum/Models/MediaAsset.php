<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class MediaAsset extends OrganizationOwnedCurriculumModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['uploaded_at' => 'immutable_datetime'];
    }
}
