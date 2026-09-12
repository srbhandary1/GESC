<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $factor_type
 * @property string|null $secret_ciphertext
 * @property string $status
 * @property CarbonImmutable|null $pending_expires_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $replaced_at
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $last_used_time_step
 */
final class PrivilegedMfaFactor extends Model
{
    use HasUlids;

    public const string TYPE_TOTP = 'totp';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_REPLACED = 'replaced';

    public const string STATUS_REVOKED = 'revoked';

    public const string STATUS_ABANDONED = 'abandoned';

    protected $guarded = ['*'];

    protected $hidden = [
        'secret_ciphertext',
        'last_used_time_step',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pending_expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'replaced_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_used_time_step' => 'integer',
        ];
    }
}
