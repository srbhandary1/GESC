<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Role extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const string DIRECT_LEARNER_KEY = 'learner';

    public const string DIRECT_TRAINER_KEY = 'trainer';

    public const string DIRECT_ORGANIZATION_ADMIN_KEY = 'organization_admin';

    public const string DIRECT_CONTENT_REVIEWER_KEY = 'content_reviewer';

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'organization_id',
        'key',
        'is_system',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<Membership, $this>
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_roles')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
