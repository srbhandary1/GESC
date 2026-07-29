<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Organization extends Model
{
    use HasUlids;

    public const string DIRECT_SYSTEM_KEY = 'global-english-direct';

    public const string KIND_PLATFORM_DIRECT = 'platform_direct';

    public const string KIND_INSTITUTION = 'institution';

    public const string STATUS_ACTIVE = 'active';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'system_key',
        'name',
        'kind',
        'status',
        'timezone',
    ];

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @return HasMany<Cohort, $this>
     */
    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    protected static function booted(): void
    {
        self::updating(static function (self $organization): void {
            if (
                $organization->getOriginal('system_key') !== null
                && $organization->isDirty('system_key')
            ) {
                throw new LogicException('An organization system key is immutable.');
            }
        });
    }
}
