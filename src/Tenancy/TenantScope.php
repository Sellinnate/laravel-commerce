<?php

declare(strict_types=1);

namespace Selli\Commerce\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Selli\Commerce\Contracts\TenantContext;

/**
 * Global scope that constrains every domain query to the current tenant.
 * A manager of tenant A cannot, by construction, read tenant B's rows.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->currentTenantId();

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}
