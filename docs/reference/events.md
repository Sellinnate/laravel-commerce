---
title: "Events Reference"
description: "The full catalogue of cart and order domain events — when each fires and its payload — plus listener, queue and broadcast notes."
type: reference
---

# Events Reference

Every domain occurrence is a standard Laravel event under `Selli\Commerce\Events\Cart\*` or `Selli\Commerce\Events\Order\*`. All implement `Selli\Commerce\Audit\Contracts\Recordable`, so they are also persisted to the [immutable trail](/concepts/audit-and-events). The core **emits**; what listens is entirely yours.

## Cart events

| Event | When emitted | Payload highlights |
| --- | --- | --- |
| `CartCreated` | A cart is opened | the `Cart` |
| `ItemAddedToCart` | A purchasable is added | `Cart`, `CartItem`, quantity |
| `ItemUpdatedInCart` | A line quantity/options change | `Cart`, `CartItem`, old/new quantity |
| `ItemRemovedFromCart` | A line is removed | `Cart`, the removed `CartItem` |
| `CartCleared` | The cart is emptied | the `Cart` |
| `CartMerged` | One cart is merged into another | source `Cart`, target `Cart`, strategy |
| `CartAbandoned` | A cart becomes abandoned | the `Cart` |
| `CartExpired` | A cart passes its TTL | the `Cart` |

## Order events

| Event | When emitted | Payload highlights |
| --- | --- | --- |
| `OrderPlaced` | A cart converts to an order | the `Order`, source cart id |
| `OrderConfirmed` | Order → confirmed | the `Order`, actor, reason |
| `OrderProcessing` | Order → processing | the `Order`, actor, reason |
| `OrderCompleted` | Order → completed | the `Order`, actor, reason |
| `OrderCancelled` | Order → cancelled | the `Order`, actor, reason |
| `OrderRefunded` | Order → refunded | the `Order`, actor, reason |
| `OrderStateTransitioned` | Any order state change (generic) | `Order`, `from_state`, `to_state`, actor, reason |

::: callout info "Generic plus specific"
Each state change emits the generic `OrderStateTransitioned` **and** a specific event (e.g. `OrderConfirmed`). Listen to the generic one for cross-cutting concerns like logging; listen to the specific ones for targeted side effects.
:::

## Inventory events

Emitted by the [Inventory module](/modules/inventory/overview) under `Selli\Commerce\Events\Inventory\*` when it is enabled. Each carries the purchasable, the quantity, the warehouse and the reference (cart/order) that caused it.

| Event | When emitted | Payload highlights |
| --- | --- | --- |
| `StockReserved` | Stock is held for a cart (add-to-cart timing) | purchasable, quantity, warehouse, cart ref |
| `StockReleased` | A hold is released (removed / TTL elapsed) | purchasable, quantity, warehouse, ref |
| `StockDepleted` | A movement drives `on_hand` to zero or below | purchasable, resulting on_hand, warehouse |
| `BackorderCreated` | An order ships beyond stock under an allowing policy | purchasable, backordered quantity, order ref |

These are the hooks for automatic reordering, low-stock alerts and ERP synchronisation — the core emits them and imposes nothing.

## Listening

They are ordinary Laravel events — register them however you prefer:

```php
use Illuminate\Support\Facades\Event;
use Selli\Commerce\Events\Order\OrderPlaced;

Event::listen(OrderPlaced::class, function (OrderPlaced $event) {
    Mail::to($event->order->customer_id)->send(new OrderReceipt($event->order));
});
```

Or a dedicated listener class:

```php
use Selli\Commerce\Events\Cart\CartAbandoned;

class SendAbandonedCartReminder
{
    public function handle(CartAbandoned $event): void
    {
        // nudge the shopper
    }
}
```

## Queue & broadcast

Because they are standard events, the usual contracts apply. Implement `ShouldQueue` on a listener to run it off the request cycle, or `ShouldBroadcast` on an event to push it to your front end:

```php
class ProvisionAccess implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        // heavy work, queued
    }
}
```

::: callout tip "Audit is automatic"
You never need a listener just to record history — the [level-1 trail](/concepts/audit-and-events) persists every Recordable event to `commerce_domain_events` automatically. Your listeners are for *reactions*, not bookkeeping.
:::

See also: [Audit & Events](/concepts/audit-and-events) · [Order](/concepts/order) · [Database schema](/reference/database-schema).
