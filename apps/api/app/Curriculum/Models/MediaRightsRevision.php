<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

final class MediaRightsRevision extends OrganizationOwnedCurriculumModel
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_WITHDRAWN = 'withdrawn';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permitted_release_channels' => 'array',
            'permitted_territories' => 'array',
            'sharing_allowed' => 'boolean',
            'derivative_use_allowed' => 'boolean',
            'effective_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
        ];
    }
}
