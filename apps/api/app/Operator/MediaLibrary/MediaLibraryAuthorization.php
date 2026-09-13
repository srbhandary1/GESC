<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class MediaLibraryAuthorization
{
    /** @var list<string> */
    public const array MEDIA_PERMISSIONS = [
        'media.library.read',
        'media.upload',
        'media.quarantine.review',
        'media.metadata.manage',
        'media.replace',
        'media.retire',
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
            throw new MediaLibraryRejected;
        }
    }

    public function assertRead(User $actor): void
    {
        foreach (self::MEDIA_PERMISSIONS as $permission) {
            if ($this->allows($actor, $permission)) {
                return;
            }
        }

        throw new MediaLibraryRejected;
    }

    public function context(User $actor): OrganizationContext
    {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || ! hash_equals($context->actorUserId, (string) $actor->getKey())
        ) {
            throw new MediaLibraryRejected;
        }

        return $context;
    }

    public function actorMembership(User $actor): stdClass
    {
        $context = $this->context($actor);
        $membership = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('id', $context->membershipId)
            ->where('user_id', (string) $actor->getKey())
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['id', 'user_id']);

        if (! $membership instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return $membership;
    }
}
