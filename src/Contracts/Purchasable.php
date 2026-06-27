<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Brick\Money\Money;

/**
 * Any host-application model becomes purchasable by implementing this contract.
 *
 * The package never owns the catalogue: it binds to whatever the host sells
 * through this single interface and freezes an immutable snapshot when a cart
 * line becomes an order line.
 */
interface Purchasable
{
    /**
     * Stable identity of the purchasable (ULID / primary key) as a string.
     */
    public function getPurchasableId(): string;

    /**
     * Logical type — the morph alias registered for the host model.
     */
    public function getPurchasableType(): string;

    /**
     * Human description captured at purchase time.
     */
    public function getName(): string;

    /**
     * Base unit price for the given ISO-4217 currency code.
     */
    public function getUnitPrice(string $currency): Money;

    /**
     * Attributes to freeze into the order snapshot (sku, variants, meta).
     *
     * @return array<string, mixed>
     */
    public function getPurchasableData(): array;

    /**
     * Whether the requested quantity can be purchased.
     *
     * Delegates to the host or to the Inventory module when active.
     */
    public function isAvailable(int $quantity): bool;
}
