<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $branch_id
 * @property int $version
 * @property CarbonImmutable|null $archived_at
 */
final class Cohort extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'organization_id',
        'branch_id',
        'key',
        'status',
        'version',
        'archived_at',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
