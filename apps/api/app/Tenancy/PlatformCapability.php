<?php

declare(strict_types=1);

namespace App\Tenancy;

enum PlatformCapability: string
{
    case FoundationTenantBootstrap = 'foundation.tenant.bootstrap';
    case AuthenticationSessionLifecycle = 'authentication.session.lifecycle';
    case MobileCredentialLifecycle = 'authentication.mobile_credential.lifecycle';
    case PrivilegedMfaLifecycle = 'authentication.privileged_mfa.lifecycle';
    case AccountRecoveryManagement = 'platform.account_recovery.manage';
    case ContentEmergencySuspend = 'content.emergency-suspend';
}
