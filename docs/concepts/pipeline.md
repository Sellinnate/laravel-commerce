---
title: "Calculation Pipeline"
description: "A deterministic, ordered pipeline of calculators that builds a fully traceable Calculation — with centralised rounding applied last."
type: concept
---

# Calculation Pipeline

Pricing maths is where commerce engines go to die: discounts stacking in the wrong order, tax applied before or after a coupon, totals that don't add up. `selli/commerce` answers this with a **deterministic, ordered pipeline** that produces a fully traceable result.

## The result objects

A run produces a `Selli\Commerce\Calculation\Calculation`:

| Member | Returns |
| --- | --- |
| `currency` | The calculation currency. |
| `lines()` | A list of `CalculationLine`. |
| `adjustments()` | Cart-level `Adjustment`s. |
| `itemsSubtotal()` | [Money](/concepts/money) — sum of line subtotals. |
| `discountTotal()` | Money — total discounts (as a positive figure). |
| `taxTotal()` | Money — total tax. |
| `shippingTotal()` | Money — total shipping. |
| `grandTotal()` | Money — the rounded payable total. |
| `rawGrandTotal()` | Money — the total **before** final rounding. |
| `breakdown()` | An array — the full, serialisable trace. |

Each `CalculationLine` has `id`, `purchasableType`, `purchasableId`, `name`, `quantity`, `unitPrice` (Money), `subtotal()`, `total()` and its own `adjustments()`.

## Adjustments

Every modifier — a discount, a tax, a fee, a shipping charge — is an immutable `Adjustment`:

| Field | Meaning |
| --- | --- |
| `type` | `AdjustmentType`: `Subtotal`, `Promotion`, `Discount`, `Shipping`, `Tax`, `GiftCard`, `Fee`. |
| `label` | Human-readable description. |
| `amount` | Signed [Money](/concepts/money) — **discounts are negative**. |
| `source` | The calculator / origin identifier. |
| `affectsTotal` | Whether it changes the grand total. |
| `data` | Arbitrary structured detail. |

Because adjustments are signed and labelled, the breakdown reads like an itemised receipt — nothing is hidden inside a net figure.

## Inclusive vs exclusive: affectsTotal

::: callout info "Reported but not double-added"
Inclusive tax is already baked into the unit price. It must be **shown** on the receipt but must **not** be added to the total again. Such an adjustment is recorded with `affectsTotal = false`: it appears in the breakdown for transparency, while the grand total ignores it. Exclusive tax uses `affectsTotal = true`.
:::

## Ordering is everything

The `Selli\Commerce\Calculation\Pipeline` runs an ordered list of `Calculator` objects. The order is **config-driven** via `commerce.pipeline`, an array of calculator class names. Order determines the maths: apply a promotion before tax and the customer is taxed on the discounted amount; reverse it and they are not. You control that explicitly.

```php
// config/commerce.php
'pipeline' => [
    \Selli\Commerce\Calculation\Calculators\SubtotalCalculator::class,
    \App\Commerce\LoyaltyDiscountCalculator::class,
    // tax, shipping, fees…
    \Selli\Commerce\Calculation\Calculators\GrandTotalCalculator::class,
],
```

`GrandTotalCalculator` **always runs last** and applies the [`RoundingStrategy`](/concepts/money). Rounding is centralised here so every total rounds once, consistently, and in a currency-aware way (JPY 0 decimals, BHD 3 decimals).

## Adding a calculator

A `Calculator` implements two methods:

```php
namespace Selli\Commerce\Contracts;

use Selli\Commerce\Calculation\Calculation;

interface Calculator
{
    public function apply(Calculation $calculation): void;
    public function identifier(): string;
}
```

`apply()` inspects the in-progress `Calculation` and pushes `Adjustment`s onto lines or the cart. `identifier()` is a stable string recorded as each adjustment's `source`. Register the class in `commerce.pipeline` at the right position — almost always **before** `GrandTotalCalculator`.

See the full walkthrough in the [custom calculator guide](/guides/custom-calculator).

## Traceability

Call `breakdown()` for a serialisable, line-by-line account of how the grand total was reached: every subtotal, every signed adjustment, its source and whether it affected the total. This is what you store on the order, render on an invoice, or diff in a test. The pipeline is pure — same inputs, same output, every time.

::: callout tip "Modules build on this seam"
The [Pricing](/modules/pricing/overview) and [Tax](/modules/tax/overview) modules are exactly this seam in action: when enabled, they auto-compose their own calculators into the pipeline (Promotion → Coupon → Tax → Gift card → GrandTotal). Leave a module off and it contributes nothing; or take full manual control by listing calculator classes in `commerce.pipeline`. Either way you can add your own calculators (fees, shipping) alongside them.

The [Inventory](/modules/inventory/overview) module is **not** part of total calculation — it plugs into the separate stock-reservation and [order placement](/concepts/order) flow.
:::

See also: [Money](/concepts/money) · [Cart](/concepts/cart) · [Configuration](/reference/configuration).
