---
title: "Cart"
description: "The mutable cart aggregate, its lifecycle, and the CartManager operations — idempotent add, distinct option lines, merge strategies and typed exceptions."
type: concept
---

# Cart

The cart is the engine's mutable workspace. It collects [`Purchasable`](/concepts/purchasable) lines, binds them live to your catalogue, and feeds the [calculation pipeline](/concepts/pipeline). Carts are pluggable, tenant-scoped and never silently lossy.

## The aggregate

`Selli\Commerce\Cart\Models\Cart` carries:

| Property | Notes |
| --- | --- |
| `id` | ULID primary key. |
| `tenant_id` | Set automatically from the [TenantContext](/concepts/multi-tenancy). |
| `owner_type` / `owner_id` | Optional polymorphic owner (a user, a session token…). |
| `currency` | Fixed for the cart's life; all lines must agree. |
| `status` | `CartStatus` enum — see below. |
| `metadata` | Free-form array. |
| `expires_at` | Drives abandonment / expiry. |

It exposes `items()` (hasMany `CartItem`), `isEmpty()` and `isMutable()`. A `CartItem` stores `purchasable_type`, `purchasable_id`, `name`, `quantity`, `unit_price` ([Money](/concepts/money)), `options` and `metadata`.

## Lifecycle

```text
            ┌─────────┐   PlaceOrder    ┌───────────┐
            │ Active  │ ──────────────▶ │ Converted │
            └────┬────┘                 └───────────┘
                 │ inactivity
                 ▼
            ┌───────────┐   ttl elapsed   ┌─────────┐
            │ Abandoned │ ──────────────▶ │ Expired │
            └───────────┘                 └─────────┘
```

The `CartStatus` enum has `Active`, `Merged`, `Converted`, `Abandoned`, `Expired`. Only an **Active** cart is mutable; mutating any other state throws `CartNotMutableException`.

## CartManager

Resolve the manager from the container and drive everything through it:

```php
use Selli\Commerce\Cart\CartManager;

$manager = app(CartManager::class);
```

| Method | Purpose |
| --- | --- |
| `find($id)` | Load a cart by id. |
| `create($currency, $ownerType, $ownerId)` | Open a new cart. |
| `forOwner($ownerType, $ownerId, $currency)` | Find the owner's active cart or create one. |
| `add($cart, $p, $qty, $options, $metadata)` | Add a purchasable. Returns the `CartItem`. |
| `setQuantity($cart, $item, $qty)` | Set an absolute quantity. |
| `remove($cart, $item)` | Remove a line. |
| `clear($cart)` | Empty the cart. |
| `merge($source, $target, $strategy)` | Merge one cart into another. |
| `calculate($cart)` | Run the pipeline lazily — no persistence. |
| `recalculate($cart)` | Re-resolve live unit prices, persist, then calculate. |

```php
$cart = $manager->forOwner('user', (string) $user->id, 'EUR');
$manager->add($cart, $product, quantity: 2);
$calc = $manager->calculate($cart); // a Calculation, nothing written
```

::: callout info "calculate vs recalculate"
`calculate()` is read-only and lazy — perfect for rendering. `recalculate()` re-binds each line to its live `Purchasable`, refreshes unit prices (the "list price changed" case), persists the cart, then calculates. Call it before checkout.
:::

## Idempotent add

With `cart.idempotent_add` enabled (the default), adding the same purchasable **with the same options** increments the existing line instead of creating a duplicate. This makes "Add to cart" safe to click twice and safe to retry.

## Options make distinct lines

Two lines for the same product are only merged when their `options` match. A red size-M shirt and a blue size-L shirt are **distinct lines** even though they share a `purchasable_id`. Options are part of the line's identity.

```php
$cart->add($cart, $shirt, 1, options: ['size' => 'M', 'colour' => 'red']);
$cart->add($cart, $shirt, 1, options: ['size' => 'L', 'colour' => 'blue']);
// → two separate items
```

## Merge strategies

When a guest signs in, merge their session cart into their account cart. The `MergeStrategy` enum decides how colliding lines combine:

| Strategy | Behaviour on a collision |
| --- | --- |
| `KeepHighestQuantity` | Keep the larger of the two quantities. |
| `Sum` | Add the quantities together. |
| `Replace` | The source line overwrites the target line. |

```php
use Selli\Commerce\Enums\MergeStrategy;

$cart->merge($guestCart, $userCart, MergeStrategy::Sum);
// guestCart is marked Merged
```

The default strategy comes from `cart.merge_strategy`.

## Typed exceptions, never silent false

The engine signals problems with [typed domain exceptions](/concepts/audit-and-events), so failures are explicit and catchable:

| Exception | Raised when |
| --- | --- |
| `InvalidQuantityException` | A quantity is zero or negative. |
| `ProductNotAvailableException` | `isAvailable($qty)` returns false. |
| `CurrencyMismatchException` | A line's currency differs from the cart's. |
| `CartNotMutableException` | The cart is not Active. |
| `EmptyCartException` | An empty cart is sent to checkout. |

All extend the abstract `CommerceException`.

## Storage driver

The cart layer is backed by the `CartRepository` contract, selected by `cart.driver`. The default is a database driver; a cache-backed store (via `cart.cache_store` + `cart.ttl`) suits high-throughput, short-lived guest carts. Either way, the `CartManager` API is identical — the driver is an implementation detail.

See also: [Pipeline](/concepts/pipeline) · [Order](/concepts/order) · [Configuration](/reference/configuration).
