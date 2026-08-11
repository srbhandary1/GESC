<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $recovery_identity_id
 * @property string $purpose
 * @property string|null $privileged_recovery_case_id
 * @property string $lookup_digest
 * @property string $token_digest
 * @property string $status
 * @property int $failed_attempts
 * @property string $delivery_outcome
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $superseded_at
 */
final class PasswordRecoveryChallenge extends Model
{
    use HasUlids;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_CONSUMED = 'consumed';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_SUPERSEDED = 'superseded';

    public const string PURPOSE_PASSWORD_RESET = 'password_reset';

    public const string PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT = 'excluded_credential_establishment';

    protected $guarded = ['*'];

    protected $hidden = ['lookup_digest', 'token_digest'];

    /** @return array<string, string> */
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
