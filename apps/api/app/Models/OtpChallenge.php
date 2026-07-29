<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $destination_digest
 * @property string $code_digest
 * @property int $failed_attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $superseded_at
 */
final class OtpChallenge extends Model
{
    use HasUlids;

    protected $guarded = ['*'];

    protected $hidden = [
        'destination_digest',
        'code_digest',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }
}
