<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

/**
 * Writes stock movements. The core calls this at two points: the cart holds
 * stock while shopping (when reservations are timed to add-to-cart), and
 * PlaceOrder fulfils the order's lines under a lock inside its own transaction.
 * When the Inventory module is off, the null implementation makes every method
 * a no-op so the core runs unchanged.
 */
interface StockKeeper
{
    /**
     * Reserve and ship the order's lines under a row lock, inside the caller's
     * transaction. Returns the lines that were backordered (empty when all were
     * in stock). Throws InsufficientStockException when short of stock and the
     * backorder policy denies it. Any holds the originating cart placed are
     * consumed rather than double-counted.
     *
     * @param  list<array{type: string, id: string, quantity: int, name: string}>  $lines
     * @return list<array{type: string, id: string, quantity: int}>
     */
    public function fulfillOrder(string $orderId, array $lines, ?string $tenantId, ?string $cartId = null): array;

    /**
     * Hold an absolute quantity of a purchasable for a cart line (add-to-cart
     * timing). Replaces any previous hold this cart had for the purchasable, so
     * idempotent adds and quantity changes converge on the current line total.
     */
    public function hold(string $cartId, string $type, string $id, int $quantity, ?string $tenantId): void;

    /**
     * Release every active hold a reference (cart or order) is keeping.
     */
    public function release(string $referenceType, string $referenceId, ?string $tenantId): void;
}
