<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $user_id
 * @property string $password_hash
 * @property string $algorithm
 * @property int $policy_version
 * @property int $credential_version
 * @property CarbonImmutable $established_at
 * @property CarbonImmutable $changed_at
 * @property CarbonImmutable|null $compromised_at
 */
final class PasswordCredential extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected $hidden = ['password_hash'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'policy_version' => 'integer',
            'credential_version' => 'integer',
            'established_at' => 'immutable_datetime',
            'changed_at' => 'immutable_datetime',
            'compromised_at' => 'immutable_datetime',
        ];
    }
}
