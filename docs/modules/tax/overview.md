---
title: "Tax"
description: "An optional module that resolves jurisdiction-aware tax rates and inserts a TaxCalculator into the pipeline — recording per-line, explainable tax frozen onto every order."
---

# Tax

The Tax module adds jurisdiction-aware tax to the calculation engine. It resolves a rate for each cart line from its **tax category** and the buyer's **jurisdiction**, computes the tax per line, and freezes the result onto the order. Like every other adjustment it stays [explainable](/concepts/pipeline): each tax line records its category, its rate in basis points, and whether the price was inclusive.

## Enabling the module

The module is toggled by a single flag and is **on by default**:

```php
// config/commerce.php
'modules' => [
    'tax' => true,
],
```

When enabled it:

- inserts a `TaxCalculator` into the [calculation pipeline](/concepts/pipeline) — after promotions and coupons, before gift cards;
- binds a `TaxResolver` (the bundled [`TableTaxResolver`](/modules/tax/jurisdiction)) into the container.

::: callout warning "When the module is off"
With `commerce.modules.tax` set to `false`, nothing is taxed. The `NullTaxResolver` is bound, no `TaxCalculator` is added, and the grand total is computed without any tax line.
:::

## Where it sits in the pipeline

```
PromotionCalculator
  → CouponDiscountCalculator
  → TaxCalculator
  → GiftCardCalculator
  → GrandTotalCalculator
```

Tax runs **after** promotions and coupons, so it is computed on the discounted base — cart-level discounts are allocated to lines in proportion to their subtotal. It runs **before** gift cards, which tender against the running payable total. See [Inclusive vs exclusive](/modules/tax/inclusive-exclusive) for how the base is built.

## Tax rates

Rates live in the tenant-scoped model `Selli\Commerce\Tax\Models\TaxRate`:

| Field | Notes |
| --- | --- |
| `tenant_id` | Scoped to the [tenant](/concepts/multi-tenancy). |
| `category` | e.g. `'standard'`, `'reduced'`, `'exempt'`. |
| `country` | ISO `CHAR(2)` country code. |
| `region` | Nullable region/state; a region-specific rate beats a country-wide one. |
| `name` | Human label for the rate. |
| `rate` | Integer **basis points** — `2200` means 22.00%. |
| `priority` | Higher priority wins among otherwise-equal matches. |
| `starts_at` / `ends_at` | Validity window. |
| `active` | Boolean on/off switch. |

See [Jurisdiction & resolution](/modules/tax/jurisdiction) for the full precedence rules.

## A worked example

Create a rate, set the cart's tax context, and watch tax appear:

```php
use Selli\Commerce\Tax\Models\TaxRate;
use Selli\Commerce\Cart\CartManager;

TaxRate::create([
    'category' => 'standard',
    'country'  => 'IT',
    'region'   => null,
    'name'     => 'IVA 22%',
    'rate'     => 2200,        // 22.00% in basis points
    'priority' => 0,
    'active'   => true,
]);

$cart = app(CartManager::class);
$cart->setTaxContext($cart, ['country' => 'IT', 'region' => null]);
```

With `prices_include_tax = true` (the default), a €122.00 line reports €22.00 of tax derived from the gross while the total stays €122.00. With it set to `false`, a €100.00 line gains €22.00 on top → €122.00. Both cases are covered in [Inclusive vs exclusive](/modules/tax/inclusive-exclusive).

## Tax categories per purchasable

A host model may declare its own category by implementing the optional contract `Selli\Commerce\Contracts\Taxable`:

```php
use Selli\Commerce\Contracts\Taxable;

class Ebook extends Model implements Taxable
{
    public function getTaxCategory(): ?string
    {
        return 'reduced';
    }
}
```

When a `Taxable` is added, `CartManager::add` **freezes** the returned category onto the cart line (in line metadata as `tax_category`). The calculator reads that frozen value; if the purchasable declares none, it falls back to `config('commerce.tax.default_category')`. A single cart can therefore mix standard, reduced and exempt lines.

## Configuration

| Key | Purpose |
| --- | --- |
| `commerce.modules.tax` | Enable or disable the whole module (default `true`). |
| `commerce.tax.prices_include_tax` | Whether catalogue prices already contain tax (default `true`). |
| `commerce.tax.default_category` | Category used when a purchasable declares none (default `'standard'`). |
| `commerce.tax.reverse_charge` | Allow the B2B intra-EU reverse charge (default `true`). |

## Learn more

::: card "Inclusive vs exclusive"
The two pricing modes, the exact formulas, worked €-examples and how tax is frozen per line. See [Inclusive vs exclusive](/modules/tax/inclusive-exclusive).
:::

::: card "Jurisdiction & resolution"
Country/region precedence, the `TaxResolver` contract, custom external providers, the tax context, exemptions and reverse charge. See [Jurisdiction & resolution](/modules/tax/jurisdiction).
:::

See also: [Pipeline](/concepts/pipeline) · [Money](/concepts/money) · [Configuration](/reference/configuration).
