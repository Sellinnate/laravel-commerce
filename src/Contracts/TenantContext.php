<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

/**
 * Tenancy-agnostic notion of "who is the current tenant".
 *
 * The core never marries a tenancy library: it asks this contract for the
 * current tenant id and applies scoping transparently. Single-tenant apps
 * leave it null and pay no complexity.
 */
interface TenantContext
{
    /**
     * The current tenant identifier, or null when not in a tenant context.
     */
    public function currentTenantId(): ?string;

    /**
     * Whether a tenant is currently active.
     */
    public function hasTenant(): bool;
}
