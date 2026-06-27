---
title: "Pricing & Promotions"
description: "An optional module that adds price books, coupons, promotions and gift cards — composed into the calculation pipeline as ordered, explainable adjustments."
---

# Pricing & Promotions

The Pricing module turns the bare calculation engine into a full pricing system. It adds segmented **price books**, a condition→action **promotion** engine, validated **coupons**, and balance-bearing **gift cards** — each one expressed as ordered, signed adjustments so the [breakdown](/concepts/pipeline) stays explainable.

## Enabling the module

The module is toggled by a single flag and is **on by default**:

```php
// config/commerce.php
'modules' => [
    'pricing' => true,
],
```

When enabled it:

- replaces the default `PriceResolver` with the price-book-aware [`PriceBookResolver`](/modules/pricing/price-books);
- registers the Promotion, Coupon and Gift Card calculators into the [calculation pipeline](/concepts/pipeline);
- binds the coupon and gift-card validators into the container.

::: callout warning "When the module is off"
With `commerce.modules.pricing` set to `false`, the engine falls back to plain purchasable pricing and the pipeline contains no pricing calculators. Calling `applyCoupon()` or `applyGiftCard()` then throws `PricingModuleDisabledException`.
:::

## How it plugs into the pipeline

When the module is on, the calculators are auto-composed in this order:

```
PromotionCalculator
  → CouponDiscountCalculator
  → (TaxCalculator, future)
  → GiftCardCalculator
  → GrandTotalCalculator
```

Promotions run first so coupons apply to a subtotal already net of promotions. Gift cards run near the end as a **tender** against the running payable total — after promotions, discounts and tax — so they can never push the grand total below zero. `GrandTotalCalculator` always runs last and centralises [rounding](/concepts/money). Every amount is a `Brick\Money\Money` in minor units.

## The four sub-features

::: card "Price books"
Segment- and quantity-aware price lists with validity windows and priority. See [Price books](/modules/pricing/price-books).
:::

::: card "Coupons"
Customer-entered codes — percentage or fixed — with usage limits, minimum spend and currency rules. See [Coupons](/modules/pricing/coupons).
:::

::: card "Promotions"
Automatic, rule-driven discounts with stacking policies and free shipping. See [Promotions](/modules/pricing/promotions).
:::

::: card "Gift cards"
Stored-balance tenders applied against the payable total, with a redemption ledger. See [Gift cards](/modules/pricing/gift-cards).
:::

## Events

Every pricing action emits a standard Laravel event in `Selli\Commerce\Events\Pricing`. They are persisted to the [audit trail](/concepts/audit-and-events):

| Event | Emitted when |
| --- | --- |
| `CouponApplied` | A coupon validates and is attached to the cart. |
| `CouponRejected` | A coupon fails validation on apply. |
| `PromotionApplied` | A matched promotion is applied on order placement. |
| `GiftCardRedeemed` | A gift-card balance is decremented on order placement. |

## Configuration

| Key | Purpose |
| --- | --- |
| `commerce.modules.pricing` | Enable or disable the whole module (default `true`). |
| `commerce.pricing.default_segment` | The fallback customer segment (default `'default'`). |
| `commerce.pricing.stacking` | The default promotion stacking policy. |

See also: [Pipeline](/concepts/pipeline) · [Money](/concepts/money) · [Configuration](/reference/configuration).
