---
title: "Inventory overview"
description: "The optional Inventory module: per-warehouse stock, available-to-promise, reservations with a TTL, oversell prevention under a lock, backorders and an append-only stock ledger — and how it plugs into the cart and order without touching the host catalogue."
type: concept
---

# Inventory overview

The Inventory module makes the engine refuse to sell what isn't there. It is **optional**: an app selling services or unlimited digital goods simply leaves it off, and `isAvailable()` falls back to the host's own answer. When it is on, availability becomes **available-to-promise** (ATP) and stock is decremented under a lock at checkout, so — with the default `backorder = deny` policy — two buyers can never both win the last unit. (Under `backorder = allow` the second sale is permitted by policy and recorded as a [backorder](/modules/inventory/warehouses-ledger).)

Toggle it with `commerce.modules.inventory` (on by default).

## What it gives you

- **Stock per warehouse.** Quantity is tracked per `Purchasable` × warehouse, not as one global number. A single-warehouse app uses one auto-created default warehouse and never thinks about the dimension.
- **Available-to-promise.** The number `isAvailable()` consults is `on_hand − reserved`, never the gross on-hand. A purchasable with no stock row is *not tracked* — availability defers to the host.
- **Reservations with a TTL.** Stock can be held at add-to-cart or only at place-order ([configurable](/modules/inventory/reservations)); an abandoned cart's hold lapses and frees the stock automatically.
- **Oversell prevention by construction.** Decrements happen under a row lock inside the `PlaceOrder` transaction — the classic race is handled, not hoped away.
- **Backorder policy.** Allow selling below zero (annotated on the order) or refuse it with a typed `InsufficientStockException` — globally or per stock item. See [Warehouses, ledger & backorder](/modules/inventory/warehouses-ledger).
- **Append-only ledger.** Every movement (receipt, adjustment, reservation, release, shipment) is an immutable row; `on_hand` is the reconcilable sum of the ledger.
- **Events.** `StockReserved`, `StockReleased`, `StockDepleted`, `BackorderCreated` — hooks for reorder, alerts and ERP sync, without the core imposing anything.

## How it plugs in

The module binds two small core seams, so the cart and order code is unchanged when it is off:

- `StockResolver` — reads ATP; the cart consults it during availability checks.
- `StockKeeper` — writes movements; the cart holds stock (add-to-cart timing) and `PlaceOrder` fulfils the order under a lock.

When the module is off, both resolve to a null object whose methods are no-ops, so the core behaves exactly as if Inventory were not installed.

```php
use Selli\Commerce\Inventory\InventoryManager;

$inventory = app(InventoryManager::class);

// Bring 100 units into the default warehouse.
$inventory->receive('product', (string) $product->id, 100);

// Available-to-promise is now 100 (null would mean "not tracked").
$inventory->availableToPromise('product', (string) $product->id, $tenantId); // 100
```

From here the cart and checkout enforce it automatically — adding more than ATP throws `ProductNotAvailableException` (unless backorders are allowed), and placing the order ships the stock.

See also: [Reservations & ATP](/modules/inventory/reservations) · [Warehouses, ledger & backorder](/modules/inventory/warehouses-ledger) · [Cart](/concepts/cart) · [Order](/concepts/order).
