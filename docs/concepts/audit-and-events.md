---
title: "Audit & Events"
description: "A two-level audit model — an always-on immutable domain-event trail and opt-in event sourcing — over a catalogue of standard Laravel events."
---

# Audit & Events

Commerce is accountable by nature: you must be able to answer *what happened, when, and to whom*. `selli/commerce` makes that automatic with a **two-level audit model** layered over ordinary Laravel events.

## The events are standard Laravel events

Every domain occurrence is a normal Laravel event you can `listen`, `queue` or `broadcast`. The core **emits** and never presumes who listens — your application, a queue worker, a webhook dispatcher, all or none.

Each event implements `Selli\Commerce\Audit\Contracts\Recordable`, the marker that opts it into the audit trail.

### Event catalogue

| Area | Event | Emitted when |
| --- | --- | --- |
| Cart | `CartCreated` | A cart is opened. |
| Cart | `ItemAddedToCart` | A purchasable is added. |
| Cart | `ItemUpdatedInCart` | A line quantity/options change. |
| Cart | `ItemRemovedFromCart` | A line is removed. |
| Cart | `CartCleared` | The cart is emptied. |
| Cart | `CartMerged` | One cart is merged into another. |
| Cart | `CartAbandoned` | A cart becomes abandoned. |
| Cart | `CartExpired` | A cart passes its TTL. |
| Order | `OrderPlaced` | A cart converts to an order. |
| Order | `OrderConfirmed` | Order → confirmed. |
| Order | `OrderProcessing` | Order → processing. |
| Order | `OrderCompleted` | Order → completed. |
| Order | `OrderCancelled` | Order → cancelled. |
| Order | `OrderRefunded` | Order → refunded. |
| Order | `OrderStateTransitioned` | Any order state change (generic). |

Full payloads are listed in the [events reference](/reference/events).

## Level 1 — the immutable trail (always on)

A wildcard listener persists **every** `Recordable` event, append-only, into `commerce_domain_events` (model `Selli\Commerce\Audit\Models\DomainEvent`):

| Column | Holds |
| --- | --- |
| `name` | The event name. |
| `subject_type` / `subject_id` | The cart or order it concerns. |
| `payload` | The event data (JSON). |
| `actor_type` / `actor_id` | Who triggered it, when known. |
| `tenant_id` | Stamped from the [TenantContext](/concepts/multi-tenancy). |
| `created_at` | When it happened. |

Nothing in this table is ever updated or deleted. In addition, every order state change is logged in `commerce_order_state_transitions` (see [Order](/concepts/order)). Together they give a complete, tamper-evident history for free — no configuration required.

Toggle the domain-event recorder with `audit.record_domain_events`.

## Level 2 — event sourcing (opt-in)

For teams that want the order aggregate itself reconstructable from its event stream, level 2 enables **event sourcing of the Order aggregate** via `spatie/laravel-event-sourcing`:

```php
'audit' => [
    'record_domain_events' => true,  // level 1
    'event_sourcing'       => false, // level 2 — opt in
],
```

Level 1 is the universally useful trail. Level 2 is a deliberate architectural choice for stricter auditing or temporal rebuild requirements.

## Listening to events

Because they are plain Laravel events, wire them however you like:

```php
use Selli\Commerce\Events\Order\OrderPlaced;

Event::listen(OrderPlaced::class, function (OrderPlaced $event) {
    // send a confirmation email, notify the warehouse, ping analytics…
});
```

```php
class NotifyWarehouse implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        // queued, off the request cycle
    }
}
```

::: callout tip "Emit, don't assume"
The engine raises events and records them; it never hard-codes side effects. Email, fulfilment, accounting and analytics are all your listeners — swap them freely without touching the core.
:::

See also: [Order](/concepts/order) · [Events reference](/reference/events) · [Database schema](/reference/database-schema).
