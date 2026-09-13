<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Operator\Exceptions\OperatorPrivacyRejected;
use App\Tenancy\OrganizationAuthorization;

final readonly class OperatorPrivacyAuthorization
{
    public const string SUPPORT_DIAGNOSTICS_READ = 'support.diagnostics.read';

    public const string PRIVACY_EXPORT_REQUEST = 'privacy.export.request';

    public const string PRIVACY_ERASURE_REQUEST = 'privacy.erasure.request';

    public function __construct(
        private OrganizationAuthorization $authorization,
    ) {}

    public function assertCanReadDiagnostics(User $actor): void
    {
        if (! $this->authorization->allows(
            $actor,
            self::SUPPORT_DIAGNOSTICS_READ,
        )) {
            throw new OperatorPrivacyRejected;
        }
    }

    public function assertCanRequestExport(User $actor): void
    {
        if (! $this->authorization->allows(
            $actor,
            self::PRIVACY_EXPORT_REQUEST,
        )) {
            throw new OperatorPrivacyRejected;
        }
    }

    public function assertCanRequestErasure(User $actor): void
    {
        if (! $this->authorization->allows(
            $actor,
            self::PRIVACY_ERASURE_REQUEST,
        )) {
            throw new OperatorPrivacyRejected;
        }
    }
}
