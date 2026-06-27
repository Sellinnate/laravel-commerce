---
title: "Money"
description: "Every amount is a Brick Money value in minor units with an explicit currency — never a float — stored as a two-column pair and rounded centrally."
---

# Money

Money bugs are silent and expensive. `selli/commerce` never represents an amount as a float or an ambiguous decimal. Every monetary value in the engine is a `Brick\Money\Money` object: an integer count of **minor units** paired with an explicit **currency**.

::: callout warning "No floats. Ever."
`0.1 + 0.2 !== 0.3` in floating point. A cent lost per line, across millions of orders, is a real liability. The engine refuses to model money as a float and you should too.
:::

## The value object

```php
use Brick\Money\Money;

Money::of('19.99', 'EUR');   // from a major-unit decimal string
Money::ofMinor(1999, 'EUR'); // from minor units (1999 cents)

$price = Money::ofMinor(1999, 'EUR');
$price->getAmount();    // BigDecimal 19.99
$price->getMinorAmount(); // BigInteger 1999
$price->getCurrency()->getCurrencyCode(); // 'EUR'
```

Amounts of differing currencies cannot be combined silently — the engine raises `CurrencyMismatchException` rather than guessing. Always source a price in the cart's currency via `getUnitPrice($currency)`.

## Two-column storage

A `Money` value persists as **two columns** via `Selli\Commerce\Casts\MoneyCast`:

| Column | Type | Holds |
| --- | --- | --- |
| `{name}_amount` | `BIGINT` | the integer minor-unit amount |
| `{name}_currency` | `CHAR(3)` | the ISO 4217 currency code |

For example a cart item's `unit_price` is stored as `unit_price_amount` + `unit_price_currency`. The cast hydrates both columns back into a single `Money` object on the model, so your code only ever sees a value object:

```php
$item->unit_price;              // Brick\Money\Money
$item->unit_price->getCurrency(); // explicit, always present
```

Storing minor units as `BIGINT` keeps arithmetic exact and the currency travels with every amount — there is no "ambient" currency to get wrong.

## Centralised, per-currency rounding

All rounding flows through a single `RoundingStrategy` binding. The default `DefaultRoundingStrategy` uses `HALF_UP`, but the important property is that rounding is **currency-aware**:

| Currency | Decimal places | `Money::ofMinor(...)` |
| --- | --- | --- |
| EUR / USD | 2 | `ofMinor(1999, 'EUR')` → 19.99 |
| JPY | 0 | `ofMinor(1999, 'JPY')` → ¥1999 |
| BHD | 3 | `ofMinor(1999, 'BHD')` → 1.999 |

Because rounding lives in one place — applied by the `GrandTotalCalculator` at the end of the [pipeline](/concepts/pipeline) — every line, adjustment and total rounds identically and consistently. Change the mode once in `commerce.rounding.mode` and the whole engine follows.

::: callout tip "Override the strategy"
Need banker's rounding, or a currency that rounds to the nearest 0.05? Bind your own `RoundingStrategy` via [`commerce.bindings`](/reference/contracts) and the entire pipeline adopts it.
:::

See also: [Pipeline](/concepts/pipeline) · [Configuration](/reference/configuration) · [Database schema](/reference/database-schema).
