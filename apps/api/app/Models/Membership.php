<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Membership extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const string TYPE_LEARNER = 'learner';

    public const string TYPE_TRAINER = 'trainer';

    public const string TYPE_ORGANIZATION_ADMIN = 'organization_admin';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_SUSPENDED = 'suspended';

    public const string STATUS_ENDED = 'ended';

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'organization_id',
        'user_id',
        'membership_type',
        'status',
        'joined_at',
        'ended_at',
        'version',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'learner_membership_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
