<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

/**
 * Reads available-to-promise (on_hand − reserved) for a purchasable. This is the
 * number the cart consults for availability when the Inventory module is on. A
 * null return means the purchasable is not stock-tracked, so availability falls
 * back to the host's {@see Purchasable::isAvailable()}.
 */
interface StockResolver
{
    public function availableToPromise(string $type, string $id, ?string $tenantId): ?int;
}
