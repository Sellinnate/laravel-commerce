<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

/**
 * Resolves a live {@see Purchasable} from its stored polymorphic reference.
 *
 * The default implementation loads via Eloquent morph map; an override can
 * load from a microservice or an external catalogue without touching the core.
 */
interface PurchasableResolver
{
    /**
     * Resolve the live purchasable, or null when it no longer exists.
     */
    public function resolve(string $type, string $id): ?Purchasable;
}
