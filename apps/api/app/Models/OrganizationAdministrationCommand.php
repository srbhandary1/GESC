<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $actor_membership_id
 * @property string $idempotency_key
 * @property string $operation
 * @property string $target_type
 * @property string $target_id
 * @property string $request_fingerprint
 * @property string|null $result_type
 * @property string|null $result_id
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $created_at
 */
final class OrganizationAdministrationCommand extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const null UPDATED_AT = null;

    protected $guarded = ['*'];

    protected $hidden = [
        'idempotency_key',
        'request_fingerprint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
