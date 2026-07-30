<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

final readonly class AcceptedAuthorityAppointment
{
    public function __construct(
        public string $appointmentId,
        public string $acceptanceEventId,
        public string $appointeeMembershipId,
    ) {}
}
