---
title: "Recipe: Place & Transition an Order"
description: "Convert a cart to an order, then walk it pending → confirmed → processing → completed, attribute each move to an actor, and read the transition log."
type: guide
---

# Recipe: Place & Transition an Order

This recipe takes a [cart](/concepts/cart) to a placed [order](/concepts/order) and then drives it through its lifecycle with [`TransitionOrderState`](/concepts/order), attributing each step to a staff member and handling illegal moves.

## 1. Place the order

```php
use Selli\Commerce\Order\Actions\PlaceOrder;

$order = app(PlaceOrder::class)->handle($cart, [
    'customer_type'    => 'user',
    'customer_id'      => (string) $user->id,
    'billing_address'  => $billing,
    'shipping_address' => $shipping,
]);

$order->state;  // Pending
$order->number; // generated
```

`PlaceOrder` runs in one transaction: final [calculation](/concepts/pipeline), frozen snapshots and totals, cart marked `Converted`, `OrderPlaced` emitted. An empty cart throws `EmptyCartException`.

## 2. Walk the lifecycle

A new order starts in `Pending`. Move it one legal edge at a time, attributing each transition to an actor and (optionally) a reason:

```php
use Selli\Commerce\Order\Actions\TransitionOrderState;

$transition = app(TransitionOrderState::class);

$transition->handle($order, 'confirmed',  by: $staff, reason: 'Payment captured');
$transition->handle($order, 'processing', by: $staff, reason: 'Picking started');
$transition->handle($order, 'completed',  by: $staff, reason: 'Shipped');
```

Each call writes an append-only `OrderStateTransition` row and emits `OrderStateTransitioned` plus the specific event (`OrderConfirmed`, `OrderProcessing`, `OrderCompleted`).

::: callout info "The actor is recorded"
Passing `by: $staff` does two things: it authorises the move through the [policy](/concepts/acl) (when the actor is `Authorizable`) and it stamps `actor_type`/`actor_id` onto the transition log so you know who did it.
:::

## 3. Handle illegal transitions

The state machine refuses any edge not in the [allowed table](/concepts/order). Skipping a step throws:

```php
use Spatie\ModelStates\Exceptions\TransitionNotFound;

try {
    // illegal: pending → completed is not an edge
    $transition->handle($order, 'completed', by: $staff);
} catch (TransitionNotFound $e) {
    report($e);
    // surface a "that step isn't allowed from here" message
}
```

Authorisation failures surface separately — an unauthorised actor is refused even on a legal edge. Both gates must pass; see the [double-check](/concepts/acl#the-transition-double-check).

## 4. Read the transition log

The full, append-only history hangs off the order:

```php
foreach ($order->transitions as $t) {
    printf(
        "%s: %s → %s by %s:%s (%s)\n",
        $t->created_at, $t->from_state, $t->to_state,
        $t->actor_type, $t->actor_id, $t->reason,
    );
}
```

```text
2026-06-27 10:01: pending → confirmed by user:42 (Payment captured)
2026-06-27 10:05: confirmed → processing by user:42 (Picking started)
2026-06-27 11:30: processing → completed by user:42 (Shipped)
```

::: callout success "Nothing is lost"
Transitions are never updated or deleted. Combined with the [domain-event trail](/concepts/audit-and-events), the order carries a complete account of who moved it where, when and why.
:::

See also: [Order](/concepts/order) · [ACL](/concepts/acl) · [Events](/reference/events).
