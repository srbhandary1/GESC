<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\AuthenticatedPrincipal;
use App\Identity\OrganizationSelectionStore;
use App\Observability\CorrelationId;
use App\Tenancy\Exceptions\AmbiguousOrganizationContext;
use App\Tenancy\Exceptions\InvalidOrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextResolver;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveOrganizationContext
{
    public function __construct(
        private OrganizationSelectionStore $selections,
        private OrganizationContextResolver $resolver,
        private OrganizationContextManager $contexts,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $selectedMembershipId = $this->selections->selected(
            $request,
            $principal,
        );

        try {
            $context = $selectedMembershipId === null
                ? $this->resolver->singleActiveMembership(
                    $principal->user,
                    CorrelationId::forRequest($request),
                )
                : $this->resolver->forSelectedMembership(
                    $principal->user,
                    $selectedMembershipId,
                    CorrelationId::forRequest($request),
                );
        } catch (
            AmbiguousOrganizationContext
            |InvalidOrganizationContext $exception
        ) {
            throw new AuthorizationException(previous: $exception);
        }

        $this->contexts->activate($context);

        try {
            return $next($request);
        } finally {
            $this->contexts->clear();
        }
    }
}
