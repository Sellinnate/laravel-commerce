---
title: "Promotions"
description: "An automatic condition→action rule engine: matched promotions discount the subtotal, grant free shipping, and combine under Exclusive, Cumulative or BestOf stacking policies."
type: concept
---

# Promotions

A **promotion** is an automatic discount: no code to enter. The engine evaluates each promotion's conditions against the cart, and every promotion that matches contributes an action. The `PromotionCalculator` runs first in the [pipeline](/concepts/pipeline), so promotions discount the subtotal before coupons and tax.

## The model

`Selli\Commerce\Pricing\Models\Promotion`:

| Field | Notes |
| --- | --- |
| `tenant_id` | Scoped to the [tenant](/concepts/multi-tenancy). |
| `name` | Human label. |
| `priority` | Integer; higher ranks first. |
| `stacking` | `Selli\Commerce\Enums\StackingPolicy`: `Exclusive`, `Cumulative`, `BestOf`. |
| `conditions` | JSON array of condition objects. |
| `actions` | JSON array of action objects. |
| `starts_at` / `ends_at` | Validity window. |
| `active` | Boolean on/off switch. |

## Conditions

A promotion matches only when **all** of its conditions hold. Each is an object with a `type` and its parameters:

| Type | Parameters | Matches when |
| --- | --- | --- |
| `cart_subtotal_min` | `amount` (minor int), `currency` | The cart subtotal is at least `amount`. |
| `item_quantity_min` | `quantity` | Total item quantity is at least `quantity`. |
| `has_purchasable` | `purchasable_type`, `purchasable_id` | The cart contains that [purchasable](/concepts/purchasable). |

## Actions

Each matched promotion applies its actions, each an object with a `type`:

| Type | Parameters | Effect |
| --- | --- | --- |
| `percentage_off` | `percent` | Subtract `percent` % of the subtotal. |
| `fixed_off` | `amount` (minor int), `currency` | Subtract a fixed amount. |
| `free_shipping` | — | Waive shipping for the cart. |

::: callout info "Never negative"
Discounts are clamped so they can never push the subtotal below zero. A `fixed_off` larger than the subtotal simply zeroes it.
:::

## Stacking policies

When several promotions match, the engine orders them by **priority desc, then discount desc**, then resolves them by the policy on the highest-ranked match:

| Policy | Behaviour |
| --- | --- |
| `Cumulative` | All matched cumulative promotions apply, stacked on top of each other. |
| `Exclusive` | If the top-priority match is exclusive, only it applies — everything else is dropped. |
| `BestOf` | Only the single highest-discount matched promotion applies. |

```php
use Selli\Commerce\Pricing\Models\Promotion;
use Selli\Commerce\Enums\StackingPolicy;

// "10% off when you spend €50+"
Promotion::create([
    'name'       => 'Spring 10',
    'priority'   => 100,
    'stacking'   => StackingPolicy::Cumulative,
    'conditions' => [
        ['type' => 'cart_subtotal_min', 'amount' => 5000, 'currency' => 'EUR'],
    ],
    'actions'    => [
        ['type' => 'percentage_off', 'percent' => 10],
    ],
    'active'     => true,
]);
```

The default stacking policy comes from `config('commerce.pricing.stacking')`.

## Free shipping

A `free_shipping` action waives the cart's shipping charge. It composes with discount actions — a single promotion can both take a percentage off and grant free shipping by listing both actions.

## The evaluator

`PromotionCalculator` applies promotions as `Promotion` [adjustments](/concepts/pipeline). The matching logic lives in `PromotionEvaluator`, a pure, testable helper:

| Method | Returns |
| --- | --- |
| `matches()` | Whether every condition holds. |
| `discount()` | The discount the promotion would apply. |
| `grantsFreeShipping()` | Whether a `free_shipping` action is present. |

Because the evaluator is pure, you can unit-test promotion behaviour without touching a cart or the database.

## On placement

When the order is placed, `PromotionApplied` is emitted once **per applied promotion**, recording it to the [audit trail](/concepts/audit-and-events).

::: callout tip "Extensible by design"
Conditions and actions are open sets keyed by `type`. New rule types slot into the same JSON shape and the same evaluator seam, so you can grow the rule engine without changing the calculator.
:::

See also: [Coupons](/modules/pricing/coupons) · [Pipeline](/concepts/pipeline) · [Configuration](/reference/configuration).
