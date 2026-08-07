<?php

declare(strict_types=1);

namespace App\Operator;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\User;
use App\Tenancy\OrganizationAuthorization;

final readonly class OperatorContentAuthorization
{
    /**
     * @var list<string>
     */
    public const array CONTENT_READ_PERMISSIONS = [
        'curriculum.author',
        'curriculum.translate.kn',
        'curriculum.review.en',
        'curriculum.review.kn',
        'curriculum.publish',
    ];

    public const string AUDIT_READ_PERMISSION = 'audit.read';

    public function __construct(
        private OrganizationAuthorization $authorization,
    ) {}

    public function assertCanReadContent(User $actor): void
    {
        foreach (self::CONTENT_READ_PERMISSIONS as $permission) {
            if ($this->authorization->allows($actor, $permission)) {
                return;
            }
        }

        throw new CurriculumOperationRejected;
    }

    public function assertCanReadAudit(User $actor): void
    {
        if (! $this->authorization->allows(
            $actor,
            self::AUDIT_READ_PERMISSION,
        )) {
            throw new CurriculumOperationRejected;
        }
    }

    public function assertCanReadPilotAnalytics(User $actor): void
    {
        if ($this->authorization->allows(
            $actor,
            self::AUDIT_READ_PERMISSION,
        )) {
            return;
        }

        $this->assertCanReadContent($actor);
    }
}
