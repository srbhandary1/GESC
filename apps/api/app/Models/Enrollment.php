<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Enrollment extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const string STATUS_ACTIVE = 'active';

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'organization_id',
        'learner_membership_id',
        'cohort_id',
        'status',
        'enrolled_at',
        'ended_at',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function learnerMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'learner_membership_id');
    }

    /**
     * @return BelongsTo<Cohort, $this>
     */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
