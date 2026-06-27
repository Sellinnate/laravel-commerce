---
title: "Coupons"
description: "Customer-entered discount codes — percentage or fixed — validated with distinct typed exceptions, enforced usage limits and minimum spend, applied as Discount adjustments and recorded on placement."
---

# Coupons

A **coupon** is a code a customer enters to claim a discount. Unlike a [promotion](/modules/pricing/promotions), it is opt-in: the code must be applied to the cart, validated, and only then does the `CouponDiscountCalculator` subtract it from the subtotal.

## The model

`Selli\Commerce\Pricing\Models\Coupon`:

| Field | Notes |
| --- | --- |
| `tenant_id` | Scoped to the [tenant](/concepts/multi-tenancy). |
| `code` | Unique per tenant (composite `tenant_id` + `code`). |
| `type` | `Selli\Commerce\Enums\CouponType`: `Percentage` or `Fixed`. |
| `value` | Percent `0-100` for `Percentage`; minor amount for `Fixed`. |
| `currency` | Nullable; the currency of a `Fixed` value. |
| `min_amount` / `min_amount_currency` | Nullable minimum spend before the code applies. |
| `usage_limit` | Nullable global redemption cap. |
| `per_customer_limit` | Nullable per-customer redemption cap. |
| `usage_count` | Running total of redemptions. |
| `starts_at` / `expires_at` | Validity window. |
| `active` | Boolean on/off switch. |

## Percentage vs fixed

```php
use Selli\Commerce\Pricing\Models\Coupon;
use Selli\Commerce\Enums\CouponType;

Coupon::create([
    'code'  => 'WELCOME10',
    'type'  => CouponType::Percentage,
    'value' => 10,            // 10% off
]);

Coupon::create([
    'code'     => 'FIVEOFF',
    'type'     => CouponType::Fixed,
    'value'    => 500,        // €5.00 off
    'currency' => 'EUR',
]);
```

## Applying and removing

Drive coupons through the [CartManager](/concepts/cart):

```php
use Selli\Commerce\Cart\CartManager;

$cart = app(CartManager::class);

$cart->applyCoupon($cart, 'WELCOME10');
$cart->coupons($cart);          // → array of applied codes
$cart->removeCoupon($cart, 'WELCOME10');
```

`applyCoupon()` validates the code. On success it stores the coupon and emits `CouponApplied`. On failure it emits `CouponRejected` and **rethrows** the typed exception below — failures are never silent.

## Typed exceptions

Validation is performed by `Selli\Commerce\Pricing\DatabaseCouponValidator`, which raises a distinct exception per failure. All live in `Selli\Commerce\Exceptions` and extend the abstract `CommerceException`:

| Exception | Raised when |
| --- | --- |
| `CouponNotFoundException` | No coupon matches the code for this tenant. |
| `CouponInactiveException` | The coupon's `active` flag is false. |
| `CouponExpiredException` | The code has expired or is not yet valid. |
| `CouponUsageLimitReachedException` | The global or per-customer limit is reached. |
| `CouponMinimumNotMetException` | The cart subtotal is below `min_amount`. |
| `CouponCurrencyMismatchException` | The coupon currency differs from the cart's. |

```php
use Selli\Commerce\Exceptions\CouponMinimumNotMetException;

try {
    $cart->applyCoupon($cart, 'FIVEOFF');
} catch (CouponMinimumNotMetException $e) {
    // tell the customer how much more they need to spend
}
```

## Usage limits and minimum spend

`usage_limit` caps total redemptions across all customers; `per_customer_limit` caps them per customer. Either being reached raises `CouponUsageLimitReachedException`. `min_amount` (with its `min_amount_currency`) sets a floor on the cart subtotal — below it the code is rejected with `CouponMinimumNotMetException`.

## At calculation time

The `CouponDiscountCalculator` applies stored coupons as `Discount` [adjustments](/concepts/pipeline) to the subtotal — which is already net of promotions, since promotions run first in the pipeline.

::: callout info "Silently skipped, never errored"
A coupon that was valid when applied but has since become invalid — expired, deactivated, limit reached — is **silently skipped** at calculation time. The cart still calculates; the stale code simply contributes nothing.
:::

## Recording redemptions

On `OrderPlaced`, a listener finalises every applied coupon: it increments `usage_count` and writes an append-only `CouponRedemption` row (`coupon_id`, `customer`, `order_id`, `amount`). The ledger is immutable, giving you an auditable history of who redeemed what.

::: callout info "How usage limits are enforced"
`usage_limit` and `per_customer_limit` are enforced when the coupon is
**applied** to the cart (`applyCoupon` → validator) — the user-facing rejection
point. A placed order is **frozen and authoritative**, so settlement never
mutates its totals: the listener simply records the actual consumption
append-only under a row lock (`usage_count` is the source of truth, idempotent
per order). Under rare concurrency (two carts applying the same limited coupon
and checking out at once) the recorded count can transiently exceed the cap;
hard, concurrency-proof enforcement requires *reserving* the coupon at
application time (decrement on apply, release on abandonment), planned for a
future release alongside gift-card and stock reservation. A `per_customer_limit`
coupon also requires an **identified customer** — it cannot be applied to an
anonymous guest cart.
:::

See also: [Cart](/concepts/cart) · [Promotions](/modules/pricing/promotions) · [Pipeline](/concepts/pipeline) · [Audit & events](/concepts/audit-and-events).
