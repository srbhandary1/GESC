<?php

declare(strict_types=1);

namespace App\Tenancy;

enum OrganizationContextSource: string
{
    case Membership = 'membership';
    case InstitutionInvite = 'institution_invite';
    case DirectProvisioning = 'direct_provisioning';
    case PlatformOperation = 'platform_operation';
}
