<?php

declare(strict_types=1);

namespace App\Learning;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class LearnerAuthority
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
    ) {}

    public function require(User $actor): OrganizationContext
    {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || $context->actorUserId !== (string) $actor->getKey()
            || ! $this->authorization->allows(
                $actor,
                'learning.participate',
            )
        ) {
            throw new LearningOperationRejected;
        }

        $membership = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('id', $context->membershipId)
            ->where('user_id', $actor->getKey())
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->where('status', Membership::STATUS_ACTIVE)
            ->when(
                DB::transactionLevel() > 0,
                static fn ($query) => $query->lockForUpdate(),
            )
            ->first(['id']);

        if (! $membership instanceof stdClass) {
            throw new LearningOperationRejected;
        }

        return $context;
    }
}
