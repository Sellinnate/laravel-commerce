---
title: "Recipe: Write a Custom Calculator"
description: "Implement the Calculator contract to add an eco-fee or loyalty discount, push a signed Adjustment, and slot it into the pipeline before GrandTotalCalculator."
type: guide
---

# Recipe: Write a Custom Calculator

The [calculation pipeline](/concepts/pipeline) is the engine's primary extension seam. Any pricing rule — a statutory eco-fee, a loyalty discount, a handling charge — is just a `Calculator` you register in config. This recipe builds two: a RAEE eco-fee and a loyalty discount.

## The contract

```php
namespace Selli\Commerce\Contracts;

use Selli\Commerce\Calculation\Calculation;

interface Calculator
{
    public function apply(Calculation $calculation): void;
    public function identifier(): string;
}
```

- `apply()` reads the in-progress `Calculation` and pushes `Adjustment`s.
- `identifier()` is a stable string recorded as each adjustment's `source` in the [breakdown](/concepts/pipeline).

## A fee calculator (eco-fee)

Adds a positive fee per unit. The amount is **signed positive** because it increases the total, and `affectsTotal` is `true` because it must be added.

```php
namespace App\Commerce;

use Brick\Money\Money;
use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Enums\AdjustmentType;

class RaeeEcoFeeCalculator implements Calculator
{
    private const FEE_MINOR = 25; // €0.25 per unit

    public function apply(Calculation $calculation): void
    {
        $currency = $calculation->currency;

        foreach ($calculation->lines() as $line) {
            $fee = Money::ofMinor(self::FEE_MINOR * $line->quantity, $currency);

            $line->adjustments()->add(new Adjustment(
                type: AdjustmentType::Fee,
                label: 'RAEE eco-fee',
                amount: $fee,                 // positive: increases the total
                source: $this->identifier(),
                affectsTotal: true,
                data: ['rate_minor' => self::FEE_MINOR],
            ));
        }
    }

    public function identifier(): string
    {
        return 'raee-eco-fee';
    }
}
```

## A discount calculator (loyalty)

A discount is the same shape with a **negative** amount and `AdjustmentType::Discount`. Here it is a cart-level 10% off the items subtotal.

```php
namespace App\Commerce;

use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Enums\AdjustmentType;

class LoyaltyDiscountCalculator implements Calculator
{
    public function apply(Calculation $calculation): void
    {
        $discount = $calculation->itemsSubtotal()
            ->multipliedBy('0.10')
            ->negated(); // negative: reduces the total

        $calculation->adjustments()->add(new Adjustment(
            type: AdjustmentType::Discount,
            label: 'Loyalty 10%',
            amount: $discount,
            source: $this->identifier(),
            affectsTotal: true,
            data: ['percentage' => 10],
        ));
    }

    public function identifier(): string
    {
        return 'loyalty-discount';
    }
}
```

::: callout tip "Signs and affectsTotal"
Push **negative** amounts for discounts, **positive** for fees/tax/shipping. Set `affectsTotal = false` only when an amount is reported but already included (e.g. inclusive tax) — see [the pipeline](/concepts/pipeline).
:::

## Register in the pipeline

Order is meaningful — discount before tax means tax on the discounted amount. Place your calculators where the maths should happen, and always **before** `GrandTotalCalculator`, which must run last to apply rounding.

```php
// config/commerce.php
'pipeline' => [
    \Selli\Commerce\Calculation\Calculators\SubtotalCalculator::class,
    \App\Commerce\LoyaltyDiscountCalculator::class, // discount first…
    \App\Commerce\RaeeEcoFeeCalculator::class,
    // …your tax/shipping calculators…
    \Selli\Commerce\Calculation\Calculators\GrandTotalCalculator::class, // always last
],
```

## Verify the trace

```php
$calc = app(CartManager::class)->calculate($cart);

$calc->discountTotal(); // includes the loyalty discount
$calc->grandTotal();    // rounded once, by GrandTotalCalculator
$calc->breakdown();     // every adjustment with its source identifier
```

::: callout warning "GrandTotalCalculator stays last"
Never place a calculator after `GrandTotalCalculator`. It applies the [RoundingStrategy](/concepts/money) and finalises the total; anything after it would not be counted.
:::

See also: [Pipeline](/concepts/pipeline) · [Money](/concepts/money) · [Configuration](/reference/configuration).
