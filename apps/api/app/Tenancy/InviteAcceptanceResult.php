<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Enrollment;
use App\Models\Membership;
use App\Models\Organization;

final readonly class InviteAcceptanceResult
{
    public function __construct(
        public Organization $organization,
        public Membership $membership,
        public ?Enrollment $enrollment,
        public bool $replayed,
    ) {}
}
