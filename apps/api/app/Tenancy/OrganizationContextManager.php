<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Tenancy\Exceptions\MissingOrganizationContext;
use Closure;
use LogicException;

final class OrganizationContextManager
{
    private ?OrganizationContext $context = null;

    public function current(): OrganizationContext
    {
        return $this->context ?? throw new MissingOrganizationContext;
    }

    public function currentOrNull(): ?OrganizationContext
    {
        return $this->context;
    }

    public function activate(OrganizationContext $context): void
    {
        if ($this->context !== null) {
            throw new LogicException('Organization context is already active.');
        }

        $this->context = $context;
    }

    public function clear(): void
    {
        $this->context = null;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(OrganizationContext $context, Closure $callback): mixed
    {
        $previous = $this->context;

        if ($previous !== null && $previous != $context) {
            throw new LogicException('A nested organization context cannot change authority.');
        }

        $this->context = $context;

        try {
            return $callback();
        } finally {
            $this->context = $previous;
        }
    }
}
