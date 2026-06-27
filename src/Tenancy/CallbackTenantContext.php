<?php

declare(strict_types=1);

namespace Selli\Commerce\Tenancy;

use Closure;
use Selli\Commerce\Contracts\TenantContext;

/**
 * Adapter that resolves the current tenant id from a closure — the seam used
 * to plug stancl/tenancy, spatie/laravel-multitenancy or a custom resolver.
 */
final class CallbackTenantContext implements TenantContext
{
    /** @var Closure(): mixed */
    private readonly Closure $resolver;

    /**
     * @param  Closure(): mixed  $resolver
     */
    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    public function currentTenantId(): ?string
    {
        $id = ($this->resolver)();

        if (is_string($id) || is_int($id)) {
            return (string) $id;
        }

        return null;
    }

    public function hasTenant(): bool
    {
        return $this->currentTenantId() !== null;
    }
}
