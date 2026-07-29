<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $client_id
 * @property string|null $selected_organization_id
 * @property string|null $selected_membership_id
 * @property string $installation_digest
 * @property CarbonImmutable $last_used_at
 * @property CarbonImmutable $absolute_expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revocation_reason
 */
final class MobileTokenFamily extends Model
{
    use HasUlids;

    protected $guarded = ['*'];

    protected $hidden = [
        'installation_digest',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'immutable_datetime',
            'absolute_expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
