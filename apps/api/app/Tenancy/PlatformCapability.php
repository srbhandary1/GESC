<?php

declare(strict_types=1);

namespace App\Tenancy;

enum PlatformCapability: string
{
    case FoundationTenantBootstrap = 'foundation.tenant.bootstrap';
    case AuthenticationSessionLifecycle = 'authentication.session.lifecycle';
    case MobileCredentialLifecycle = 'authentication.mobile_credential.lifecycle';
    case ContentEmergencySuspend = 'content.emergency-suspend';
}
