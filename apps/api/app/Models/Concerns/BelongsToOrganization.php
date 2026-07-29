<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Tenancy\OrganizationContextManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(
            'organization',
            static function (Builder $builder): void {
                $context = app(OrganizationContextManager::class)->current();

                $builder->where(
                    $builder->qualifyColumn('organization_id'),
                    $context->organizationId,
                );
            },
        );

        static::creating(static function (Model $model): void {
            $organizationId = app(OrganizationContextManager::class)
                ->current()
                ->organizationId;
            $supplied = $model->getAttribute('organization_id');

            if ($supplied !== null && $supplied !== $organizationId) {
                throw new LogicException('Cross-organization creation is prohibited.');
            }

            $model->setAttribute('organization_id', $organizationId);
        });

        static::updating(static function (Model $model): void {
            $organizationId = app(OrganizationContextManager::class)
                ->current()
                ->organizationId;

            if ($model->getOriginal('organization_id') !== $organizationId) {
                throw new LogicException('Cross-organization updates are prohibited.');
            }

            if ($model->isDirty('organization_id')) {
                throw new LogicException('Organization ownership is immutable.');
            }
        });

        static::deleting(static function (Model $model): void {
            $organizationId = app(OrganizationContextManager::class)
                ->current()
                ->organizationId;

            if ($model->getOriginal('organization_id') !== $organizationId) {
                throw new LogicException('Cross-organization deletes are prohibited.');
            }
        });
    }

    public function organizationId(): string
    {
        $organizationId = $this->getAttribute('organization_id');

        if (! is_string($organizationId)) {
            throw new LogicException('Organization-owned model has no organization identifier.');
        }

        return $organizationId;
    }
}
