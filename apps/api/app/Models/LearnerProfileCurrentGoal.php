<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class LearnerProfileCurrentGoal extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'corrected_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
