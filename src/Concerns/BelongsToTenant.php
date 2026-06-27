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
            // Only auto-stamp when tenant_id was never provided. An explicit
            // value (including an explicit null, e.g. a guest cart's order or
            // an audit row carrying its subject's tenant) is authoritative and
            // must never be replaced by the ambient context.
            if (! array_key_exists('tenant_id', $model->getAttributes())) {
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
