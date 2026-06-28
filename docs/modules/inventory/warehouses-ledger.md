---
title: "Warehouses, ledger & backorder"
description: "Per-warehouse stock, the append-only stock ledger that on_hand reconciles to, fulfilment under a lock across warehouses, and the backorder policy (deny vs allow) with a per-item override."
type: concept
---

# Warehouses, ledger & backorder

## Warehouses

Stock lives in a `Warehouse`. A single-warehouse app never creates one — the default warehouse (`commerce.inventory.default_warehouse`, code `default`) is created on first use and every automatic operation targets it. Multi-warehouse apps create warehouses with a `priority`, and fulfilment allocates from them cheapest-priority first.

```php
use Selli\Commerce\Inventory\Models\Warehouse;

Warehouse::create(['code' => 'eu-central', 'name' => 'EU Central', 'priority' => 0]);
Warehouse::create(['code' => 'overflow', 'name' => 'Overflow', 'priority' => 10]);

$inventory->receive('product', $id, 50, warehouseCode: 'eu-central');
$inventory->receive('product', $id, 50, warehouseCode: 'overflow');
// ATP is 100 across both; an order draws from eu-central first, then overflow.
```

## The append-only ledger

Every change is an immutable `StockMovement` row — never updated, never deleted:

| Type | Effect | Raised by |
| --- | --- | --- |
| `receipt` | +on_hand | `receive()` |
| `adjustment` | ±on_hand | `adjust()` (stock-take correction) |
| `reservation` | +reserved | a cart hold |
| `release` | −reserved | hold released / TTL elapsed |
| `shipment` | −on_hand (−reserved if from a hold) | order fulfilment |

`on_hand` and `reserved` on the `StockItem` are a lock-friendly **projection** of this ledger: the current position is always the reconcilable sum of the movements, so the trail is auditable end to end.

```php
$inventory->receive('product', $id, 100, reason: 'PO-4471');
$inventory->adjust('product', $id, -2, reason: 'damaged in transit');
// on_hand 98, and StockMovement sums to 98.
```

## Fulfilment under a lock

`PlaceOrder` calls the keeper inside its own transaction. For each line it:

1. consumes any holds the originating cart placed (held stock ships, not double-counts);
2. ships the remainder from on-hand stock, warehouse by warehouse, each row **locked**;
3. if still short, applies the backorder policy.

Because the decrement happens under `lockForUpdate` in the same transaction that creates the order, two concurrent checkouts of the last unit cannot both succeed — the second blocks, then sees the depleted stock and is refused. The whole order rolls back on refusal, so a failed checkout never leaves a half-shipped order.

## Backorder policy

`commerce.inventory.backorder` decides what happens when an order needs more than is available:

- **`deny`** (default) — throw `InsufficientStockException`; the order is never created.
- **`allow`** — let it through, drive `on_hand` below zero, record the shortfall on the order's `metadata._backorders`, and emit `BackorderCreated`.

A stock item can override the global policy with its `allow_backorder` column (`true`/`false`, or `null` to inherit), so a specific SKU can be back-orderable while the catalogue default is strict.

```php
config(['commerce.inventory.backorder' => 'deny']);

// This SKU is the exception — it may go on backorder.
$item = StockItem::where('purchasable_id', $id)->first();
$item->update(['allow_backorder' => true]);
```

::: callout warning "Backorders are recorded, never hidden"
An allowed backorder is the truth of a frozen order: the shortfall is written to the order and announced via `BackorderCreated`. The engine never silently rewrites stock to make the numbers look clean — `on_hand` honestly goes negative until a `receipt` replenishes it.
:::

See also: [Inventory overview](/modules/inventory/overview) · [Reservations & ATP](/modules/inventory/reservations) · [Order](/concepts/order) · [Audit & events](/concepts/audit-and-events).
