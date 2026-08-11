<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

final class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasUlids;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_SUSPENDED = 'suspended';

    public const string STATUS_CLOSED = 'closed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'display_name',
    ];

    /**
     * @return HasMany<UserIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasOne<PasswordCredential, $this>
     */
    public function passwordCredential(): HasOne
    {
        return $this->hasOne(PasswordCredential::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credential_version' => 'integer',
            'username_established_at' => 'immutable_datetime',
            'credential_cutover_at' => 'immutable_datetime',
        ];
    }
}
