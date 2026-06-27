<?php

declare(strict_types=1);

namespace Selli\Commerce\Inventory;

use Selli\Commerce\Contracts\Purchasable;
use Selli\Commerce\Contracts\StockKeeper;
use Selli\Commerce\Contracts\StockResolver;

/**
 * The no-op inventory used when the module is off (or for purchasables that are
 * not stock-tracked). Availability falls back to the host's
 * {@see Purchasable::isAvailable()} and no stock is
 * ever moved, so the core behaves exactly as if Inventory were not installed.
 */
final class NullInventory implements StockKeeper, StockResolver
{
    public function availableToPromise(string $type, string $id, ?string $tenantId): ?int
    {
        return null;
    }

    public function fulfillOrder(string $orderId, array $lines, ?string $tenantId, ?string $cartId = null): array
    {
        return [];
    }

    public function hold(string $cartId, string $type, string $id, int $quantity, ?string $tenantId): void
    {
        // no-op
    }

    public function release(string $referenceType, string $referenceId, ?string $tenantId): void
    {
        // no-op
    }
}
