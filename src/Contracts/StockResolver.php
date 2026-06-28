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

    /**
     * Whether this purchasable may be sold beyond its available stock, honouring
     * a per-item override of the global backorder policy. The cart consults this
     * so a back-orderable SKU is not blocked at add time when the global policy
     * is "deny".
     */
    public function allowsBackorder(string $type, string $id, ?string $tenantId): bool;

    /**
     * The quantity of a purchasable a reference (e.g. a cart) is already holding.
     * The cart adds this back to ATP when checking its own totals, so its own
     * add-to-cart hold never counts against itself.
     */
    public function heldQuantity(string $referenceType, string $referenceId, string $type, string $id, ?string $tenantId): int;
}
