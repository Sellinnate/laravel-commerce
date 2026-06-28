---
title: "Inclusive vs exclusive tax"
description: "The two pricing modes — tax derived from the gross or added on top — with exact formulas, worked €-examples, the discounted base, and how tax is frozen per line on the order."
type: concept
---

# Inclusive vs exclusive tax

There are two ways a catalogue price can relate to tax: the price already contains it, or the price is net and tax is added on top. The single switch `config('commerce.tax.prices_include_tax')` decides which mode the `TaxCalculator` uses. Both modes are handled by the same calculator, so the result is **impossible to get wrong by construction** — you cannot accidentally double-tax an inclusive price or forget to add tax to an exclusive one.

## Inclusive (B2C, EU default)

When `prices_include_tax = true`, the line price already contains tax. The tax is **derived from the gross** rather than added:

```text
tax = gross × rate ÷ (1 + rate)
```

It is recorded as an **informational** adjustment that does NOT add to the total again — the grand total stays equal to the gross the customer already saw.

::: card "Worked example — inclusive"
A €122.00 line at 22%:

```text
tax   = 122.00 × 0.22 ÷ 1.22 = 22.00
total = 122.00              (unchanged)
```

The customer is charged €122.00; €22.00 of that is reported as tax for the receipt.
:::

## Exclusive (B2B, US default)

When `prices_include_tax = false`, the price is net and tax is **added** on top:

```text
tax   = net × rate
total = net + tax
```

::: card "Worked example — exclusive"
A €100.00 line at 22%:

```text
tax   = 100.00 × 0.22 = 22.00
total = 100.00 + 22.00 = 122.00
```

The customer is charged €122.00; €22.00 of that is added tax.
:::

## How the grand total differs

The mode only changes whether the same €22.00 is **inside** or **on top of** the price:

| Mode | Line price | Tax reported | Grand total |
| --- | --- | --- | --- |
| Inclusive | €122.00 (gross) | €22.00 (derived) | €122.00 |
| Exclusive | €100.00 (net) | €22.00 (added) | €122.00 |

::: callout tip "One switch, no double counting"
Because the inclusive tax is informational and the exclusive tax is additive, the grand total is always correct for the configured mode. Switching `prices_include_tax` re-interprets the catalogue prices consistently across every line — there is no per-line flag to keep in sync.
:::

## Tax is computed on the discounted base

Tax never runs on the raw subtotal. It runs **after** promotions and coupons in the [pipeline](/concepts/pipeline), so cart-level discounts are first allocated to lines in proportion to their subtotal, and the rate is applied to that discounted base.

::: card "Worked example — discounted base (exclusive)"
A €100.00 line, a 10% coupon, then 22% tax:

```text
discounted base = 100.00 − 10% = 90.00
tax             = 90.00 × 0.22 = 19.80
total           = 90.00 + 19.80 = 109.80
```

The tax follows the discount automatically — you never tax an amount the customer is not actually paying.
:::

## Frozen per line on the order

Tax is computed **per line**, not on the cart total, so a cart can mix categories and rates and each line carries its own tax. When the order is placed each line's tax is frozen:

- `OrderLine.tax_total` — the tax amount for that line;
- `OrderLine.tax_detail` — the breakdown: category, rate (bps) and whether it was inclusive;
- `Order.tax_total` — the aggregate across all lines.

::: callout info "Rounding and money"
All amounts are `Brick\Money` in minor units. Tax is rounded **per line**, and rounding is centralised — see [Money](/concepts/money). A placed order is frozen and authoritative, so its tax never changes after placement.
:::

See also: [Tax overview](/modules/tax/overview) · [Jurisdiction & resolution](/modules/tax/jurisdiction) · [Pipeline](/concepts/pipeline) · [Money](/concepts/money).
