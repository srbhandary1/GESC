<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $family_id
 * @property string $token_digest
 * @property string $passport_refresh_token_id
 * @property string $access_token_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $rotated_at
 */
final class MobileRefreshToken extends Model
{
    use HasUlids;

    protected $guarded = ['*'];

    protected $hidden = [
        'token_digest',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'rotated_at' => 'immutable_datetime',
        ];
    }
}
