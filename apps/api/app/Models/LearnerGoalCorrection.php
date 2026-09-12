<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class LearnerGoalCorrection extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const null UPDATED_AT = null;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
