---
title: "Reservations & available-to-promise"
description: "How available-to-promise is computed, the two reservation timings (add-to-cart vs place-order), the TTL that frees abandoned carts, and the command that sweeps expired holds."
---

# Reservations & available-to-promise

## Available-to-promise

Availability is never the gross on-hand quantity — it is **available-to-promise**:

```text
ATP = on_hand − active (non-expired) reservations
```

This is the number `isAvailable()` consults through the cart. A purchasable with **no** stock row anywhere is treated as *not tracked*, and ATP returns `null` — availability falls back to the host's `Purchasable::isAvailable()`. Once a stock row exists (even at zero), the purchasable is tracked and ATP governs.

ATP is summed across a tenant's warehouses and always reflects the **current** moment, so a reservation that has passed its TTL stops counting immediately — before any sweep runs.

## Two reservation timings

`commerce.inventory.reserve_on` decides *when* stock is held:

| Timing | Behaviour | Use it when |
| --- | --- | --- |
| `place_order` (default) | Stock is decremented only at checkout, under a lock. Carts never hold stock. | The common case — cheapest, no abandoned-cart bookkeeping. |
| `add_to_cart` | Stock is held the moment a line is added, with a TTL, and released when the line is removed, the cart is cleared, or the TTL lapses. | High-contention drops where a cart should "claim" stock while the user checks out. |

Under `add_to_cart`, the hold tracks the line's **absolute** quantity: adding the same purchasable again, or changing the quantity, updates the hold rather than stacking it; removing the line (or clearing the cart) gives it back. A merge moves the guest cart's holds onto the surviving user cart.

```php
config(['commerce.inventory.reserve_on' => 'add_to_cart']);

$carts->add($cart, $product, 3);   // holds 3 — ATP drops by 3
$carts->setQuantity($cart, $item, 5); // hold becomes 5 (absolute)
$carts->remove($cart, $item);      // hold released — ATP restored
```

At placement the cart's holds are **consumed** (turned into shipments) rather than double-counted, so the held stock ships and ATP stays consistent.

## TTL — abandoned carts free their stock

A hold carries an `expires_at` set from `commerce.inventory.reservation_ttl` (minutes; `null` disables expiry). An expired hold no longer counts against ATP, so its stock is promised to someone else again automatically — preventing dead carts from locking up merchandise.

To actually release the rows (flip them to `released` and write the ledger movement), schedule the sweep:

```bash
php artisan commerce:inventory:release-expired
```

```php
// app/Console/Kernel.php
$schedule->command('commerce:inventory:release-expired')->everyMinute();
```

The sweep is idempotent and safe to run as often as you like.

::: callout note "Read vs write accuracy"
ATP is TTL-accurate at read time even before the sweep runs (expired holds are excluded from the live sum), while the sweep keeps the reservation rows and the ledger tidy. You never have to run the command for correctness — only for housekeeping.
:::

See also: [Inventory overview](/modules/inventory/overview) · [Warehouses, ledger & backorder](/modules/inventory/warehouses-ledger) · [Cart](/concepts/cart).
