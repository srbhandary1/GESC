<?php

declare(strict_types=1);

namespace App\Curriculum\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

abstract class OrganizationOwnedCurriculumModel extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    /**
     * Authoritative curriculum records are populated only through explicit
     * command services or trusted fixture seeders.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];
}
