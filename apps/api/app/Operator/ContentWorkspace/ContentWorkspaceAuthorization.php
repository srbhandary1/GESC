<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace;

use App\Models\User;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceRejected;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class ContentWorkspaceAuthorization
{
    /** @var list<string> */
    private const array READ_PERMISSIONS = [
        'curriculum.author',
        'curriculum.translate.kn',
        'curriculum.review.en',
        'curriculum.review.kn',
        'curriculum.publish',
    ];

    public function __construct(
        private OrganizationAuthorization $authorization,
        private OrganizationContextManager $contexts,
    ) {}

    public function allows(User $actor, string $permission): bool
    {
        return $this->authorization->allows($actor, $permission);
    }

    public function assert(User $actor, string $permission): void
    {
        if (! $this->allows($actor, $permission)) {
            throw new ContentWorkspaceRejected;
        }
    }

    public function assertRead(User $actor): void
    {
        foreach (self::READ_PERMISSIONS as $permission) {
            if ($this->allows($actor, $permission)) {
                return;
            }
        }

        throw new ContentWorkspaceRejected;
    }

    public function context(User $actor): OrganizationContext
    {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || ! hash_equals($context->actorUserId, (string) $actor->getKey())
        ) {
            throw new ContentWorkspaceRejected;
        }

        return $context;
    }

    public function actorMembership(User $actor): stdClass
    {
        $context = $this->context($actor);
        $membership = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('user_id', (string) $actor->getKey())
            ->where('id', $context->membershipId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['id', 'user_id', 'version']);

        if (! $membership instanceof stdClass) {
            throw new ContentWorkspaceRejected;
        }

        return $membership;
    }
}
