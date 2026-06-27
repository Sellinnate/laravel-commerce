<?php

declare(strict_types=1);

namespace Selli\Commerce\Tenancy;

use Selli\Commerce\Contracts\TenantContext;

/**
 * Default single-tenant context: there is no tenant. Apps that do not use
 * multi-tenancy pay no complexity.
 */
final class NullTenantContext implements TenantContext
{
    public function currentTenantId(): ?string
    {
        return null;
    }

    public function hasTenant(): bool
    {
        return false;
    }
}
