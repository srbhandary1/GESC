<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $storage_session_digest
 * @property string $storage_session_ciphertext
 * @property bool $is_privileged
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable $idle_expires_at
 * @property CarbonImmutable $absolute_expires_at
 * @property CarbonImmutable|null $mfa_verified_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revocation_reason
 */
final class AuthenticationSession extends Model
{
    use HasUlids;

    protected $guarded = ['*'];

    protected $hidden = [
        'storage_session_digest',
        'storage_session_ciphertext',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_privileged' => 'boolean',
            'started_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'idle_expires_at' => 'immutable_datetime',
            'absolute_expires_at' => 'immutable_datetime',
            'mfa_verified_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
