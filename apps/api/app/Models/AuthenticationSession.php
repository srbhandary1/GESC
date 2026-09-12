<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $selected_organization_id
 * @property string|null $selected_membership_id
 * @property string $storage_session_digest
 * @property string $storage_session_ciphertext
 * @property bool $is_privileged
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable $idle_expires_at
 * @property CarbonImmutable $absolute_expires_at
 * @property CarbonImmutable|null $mfa_verified_at
 * @property string|null $mfa_factor_id
 * @property string|null $mfa_verified_method
 * @property string|null $mfa_verified_scope
 * @property CarbonImmutable|null $password_confirmed_at
 * @property int|null $password_confirmed_credential_version
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revocation_reason
 */
final class AuthenticationSession extends Model
{
    use HasUlids;

    public const string MFA_METHOD_TOTP = 'totp';

    public const string MFA_METHOD_RECOVERY_CODE = 'recovery_code';

    public const string MFA_SCOPE_PRIVILEGED_ACTIONS = 'privileged_actions';

    public const string MFA_SCOPE_FACTOR_REPLACEMENT = 'factor_replacement';

    protected $guarded = ['*'];

    protected $hidden = [
        'storage_session_digest',
        'storage_session_ciphertext',
    ];

    /**
     * @return HasOne<AuthenticationSessionCleanupRequest, $this>
     */
    public function cleanupRequest(): HasOne
    {
        return $this->hasOne(AuthenticationSessionCleanupRequest::class);
    }

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
            'password_confirmed_at' => 'immutable_datetime',
            'password_confirmed_credential_version' => 'integer',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
