---
title: "Order"
description: "The immutable order aggregate, its guarded state machine, the append-only transition log, and conversion from a cart in a single transaction."
type: concept
---

# Order

An order is the permanent, immutable record of a sale. Where a [cart](/concepts/cart) binds live to your catalogue, an order **snapshots** everything at the moment of placement and then only moves through a guarded state machine. Orders are soft-deleted — never physically removed.

## The aggregate

`Selli\Commerce\Order\Models\Order`:

| Property | Notes |
| --- | --- |
| `id` | ULID primary key. |
| `tenant_id` | Stamped from the [TenantContext](/concepts/multi-tenancy). |
| `number` | Human-facing order number (see [OrderNumberGenerator](/reference/contracts)). |
| `customer_type` / `customer_id` | Polymorphic customer. |
| `currency` | Order currency. |
| `state` | State machine value (below). |
| `subtotal`, `discount_total`, `tax_total`, `shipping_total`, `grand_total` | All [Money](/concepts/money). |
| `billing_address` / `shipping_address` | Array snapshots. |
| `metadata` | Free-form array. |
| `placed_at` | Timestamp of placement. |

Relations: `lines()` (`OrderLine`) and `transitions()` (`OrderStateTransition`).

## Immutable order lines

Each `OrderLine` is a frozen snapshot, not a live binding:

`purchasable_type`, `purchasable_id`, `name`, `sku`, `quantity`, `unit_price`, `line_subtotal`, `discount_total`, `tax_total`, `line_total`, `snapshot` (the full `getPurchasableData()` array), `tax_detail`, `discount_detail`.

::: callout info "Why snapshot?"
If a product's price later changes, or the product is deleted entirely, the order must still report exactly what was bought and paid. The snapshot guarantees the order is a faithful historical document forever. See [Purchasable](/concepts/purchasable) for the live-vs-snapshot model.
:::

## The state machine

Built on `spatie/laravel-model-states`. States live in `Selli\Commerce\Order\States` and all extend the abstract `OrderState`: `Pending`, `Confirmed`, `Processing`, `Completed`, `Cancelled`, `Refunded`, `PartiallyRefunded`.

```text
            ┌─────────┐
            │ Pending │
            └──┬───┬──┘
     confirmed │   │ cancelled
               ▼   └──────────────┐
         ┌───────────┐            │
         │ Confirmed │            │
         └──┬─────┬──┘            ▼
 processing │     │ cancelled  ┌───────────┐
            ▼     └──────────▶ │ Cancelled │ (final)
      ┌────────────┐           └───────────┘
      │ Processing │                 ▲
      └──┬──────┬──┘                 │ cancelled
completed │      └───────────────────┘
          ▼
     ┌───────────┐  refunded   ┌──────────┐
     │ Completed │ ──────────▶ │ Refunded │ (final)
     └─────┬─────┘             └──────────┘
           │ partially_refunded     ▲
           ▼                        │ refunded
   ┌────────────────────┐           │
   │ PartiallyRefunded  │ ──────────┘
   └────────────────────┘
```

### Allowed transitions

| From | To |
| --- | --- |
| `pending` | `confirmed`, `cancelled` |
| `confirmed` | `processing`, `cancelled` |
| `processing` | `completed`, `cancelled` |
| `completed` | `refunded`, `partially_refunded` |
| `partially_refunded` | `refunded` |

`Cancelled` and `Refunded` are **final** (`isFinal()` returns true). Any transition not in this table throws `Spatie\ModelStates\Exceptions\TransitionNotFound` — there are no silent no-ops.

## Conversion: PlaceOrder

`Selli\Commerce\Order\Actions\PlaceOrder` converts a cart to an order in a **single database transaction**:

```php
use Selli\Commerce\Order\Actions\PlaceOrder;

$order = app(PlaceOrder::class)->handle($cart, [
    'customer_type'    => 'user',
    'customer_id'      => (string) $user->id,
    'billing_address'  => $billing,
    'shipping_address' => $shipping,
]);
```

Inside one transaction it: runs the final [calculation](/concepts/pipeline), freezes every line snapshot and the totals, persists the order, marks the cart `Converted`, and emits `OrderPlaced`. An empty cart throws `EmptyCartException`.

## Transitioning state

`Selli\Commerce\Order\Actions\TransitionOrderState` is the only sanctioned way to move an order:

```php
use Selli\Commerce\Order\Actions\TransitionOrderState;

$order = app(TransitionOrderState::class)->handle(
    $order,
    'confirmed',
    by: $admin,            // optional Authorizable actor
    reason: 'Payment captured',
);
```

It performs a **double check** — legality (the state machine) and permission (the [policy](/concepts/acl), when `$by` is an `Authorizable`) — then runs the spatie transition, writes an append-only `OrderStateTransition` row, and emits `OrderStateTransitioned` plus the specific event (`OrderConfirmed`, `OrderProcessing`, `OrderCompleted`, `OrderCancelled`, `OrderRefunded`).

## The transition log

Every state change is recorded in an append-only `OrderStateTransition`: `from_state`, `to_state`, `actor_type`, `actor_id`, `reason`, `created_at`. There are no updates or deletes — it is a complete, tamper-evident audit trail of the order's life. Combined with the [domain event](/concepts/audit-and-events) stream, you always know who did what, when and why.

See also: [Cart](/concepts/cart) · [Pipeline](/concepts/pipeline) · [ACL](/concepts/acl) · the [place-and-transition guide](/guides/place-and-transition).
