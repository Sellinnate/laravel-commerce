<?php

declare(strict_types=1);

namespace Selli\Commerce\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Contracts\TenantContext;
use Selli\Commerce\Tenancy\TenantScope;

/**
 * Adds transparent multi-tenant scoping: a global scope filters by the current
 * tenant and new rows are stamped with it automatically. Single-tenant apps
 * (null TenantContext) see no effect.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $tenantId = app(TenantContext::class)->currentTenantId();

                if ($tenantId !== null) {
                    $model->setAttribute('tenant_id', $tenantId);
                }
            }
        });
    }

    /**
     * Query without the tenant scope (use deliberately, e.g. in console jobs).
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
