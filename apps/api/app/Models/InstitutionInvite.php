<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $issued_by_membership_id
 * @property string|null $revoked_by_membership_id
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $code_discard_after
 * @property int $version
 */
final class InstitutionInvite extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_REVOKED = 'revoked';

    public const string STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'organization_id',
        'code_digest',
        'membership_type',
        'issued_by_membership_id',
        'role_id',
        'cohort_id',
        'status',
        'expires_at',
        'accepted_membership_id',
        'accepted_by_user_id',
        'accepted_idempotency_key',
        'accepted_request_fingerprint',
        'accepted_at',
        'revoked_by_membership_id',
        'revoked_at',
        'code_discard_after',
        'version',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'code_digest',
        'accepted_idempotency_key',
        'accepted_request_fingerprint',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Cohort, $this>
     */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function acceptedMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'accepted_membership_id');
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function issuerMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'issued_by_membership_id');
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function revokerMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'revoked_by_membership_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'code_discard_after' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
