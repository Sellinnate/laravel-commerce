<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Selli\Commerce\Cart\Models\Cart;

/**
 * Storage-agnostic persistence boundary for carts. Implementations
 * (database, session, cache) are interchangeable via config without touching
 * application logic.
 */
interface CartRepository
{
    public function find(string $id): ?Cart;

    /**
     * Find the active cart owned by the given owner within the current tenant.
     */
    public function findActiveForOwner(string $ownerType, string $ownerId): ?Cart;

    public function save(Cart $cart): Cart;

    public function delete(Cart $cart): void;
}
