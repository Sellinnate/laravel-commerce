---
title: "Jurisdiction & resolution"
description: "How a rate is resolved from country, region, priority and validity — the TaxResolver contract and RateResult, binding a custom external provider, the tax context, exemptions and the B2B intra-EU reverse charge."
---

# Jurisdiction & resolution

For each cart line the `TaxCalculator` asks a `TaxResolver` for a rate, passing the line's **tax category** and the buyer's **jurisdiction**. The jurisdiction comes from the cart's tax context, which the host sets. Without a `country`, no tax is computed.

## How a rate is resolved

The bundled `Selli\Commerce\Tax\TableTaxResolver` queries the `tax_rates` table — scoped explicitly to the jurisdiction's tenant and valid at the current time — and picks a single winner:

1. **Country, then region.** A region-specific rate (matching `region`) beats a country-wide rate (`region = null`).
2. **Priority.** Among otherwise-equal matches, the higher `priority` wins.
3. **Validity.** Only `active` rates whose `starts_at`/`ends_at` window contains now are considered.

```php
use Selli\Commerce\Tax\Models\TaxRate;

// Country-wide standard rate
TaxRate::create([
    'category' => 'standard', 'country' => 'US', 'region' => null,
    'name' => 'No state tax', 'rate' => 0, 'priority' => 0, 'active' => true,
]);

// Region-specific override — wins for California
TaxRate::create([
    'category' => 'standard', 'country' => 'US', 'region' => 'CA',
    'name' => 'CA sales tax', 'rate' => 725, 'priority' => 0, 'active' => true,
]);
```

## The resolver contract

```php
namespace Selli\Commerce\Contracts;

interface TaxResolver
{
    public function resolve(string $category, array $jurisdiction): ?RateResult;
}
```

`$jurisdiction` is `['country' => ..., 'region' => ..., 'tenant_id' => ...]`. The resolver returns a `Selli\Commerce\Tax\RateResult`, or `null` when no rate applies (which the calculator treats as no tax):

```php
namespace Selli\Commerce\Tax;

final class RateResult
{
    public function __construct(
        public readonly int $basisPoints,   // 2200 = 22.00%
        public readonly string $label,
    ) {}
}
```

The `NullTaxResolver` is bound when the module is off; it always returns `null`.

## Binding a custom resolver

To delegate to an external VAT/sales-tax provider (rate API, address validation, etc.), implement the contract and bind it via `commerce.bindings`:

```php
// config/commerce.php
'bindings' => [
    \Selli\Commerce\Contracts\TaxResolver::class => \App\Tax\AvalaraTaxResolver::class,
],
```

Your resolver receives the same `category` and `jurisdiction` and returns a `RateResult`. The calculator, the per-line freezing and the breakdown all stay identical — only the source of the rate changes.

## The tax context

The host sets jurisdiction and flags on the cart. The host is responsible for determining eligibility (e.g. VIES VAT validation) and setting `exempt` / `reverse_charge` accordingly:

```php
use Selli\Commerce\Cart\CartManager;

$cart = app(CartManager::class);

$cart->setTaxContext($cart, [
    'country'        => 'IT',
    'region'         => null,
    'exempt'         => false,
    'exempt_reason'  => null,
    'b2b'            => false,
    'vat_number'     => null,
    'reverse_charge' => false,
]);

$cart->taxContext($cart); // → reads the array back
```

### Context keys

| Key | Type | Purpose |
| --- | --- | --- |
| `country` | string | ISO `CHAR(2)` country. Without it, no tax is computed. |
| `region` | string\|null | Region/state for region-specific rates. |
| `exempt` | bool | Apply no tax and annotate the line as exempt. |
| `exempt_reason` | string\|null | Fiscal justification recorded on the exempt line. |
| `b2b` | bool | Whether the customer is a business. |
| `vat_number` | string\|null | The customer's VAT number, recorded under reverse charge. |
| `reverse_charge` | bool | Whether the intra-EU reverse charge applies (host decides). |

## Exemptions

If the context has `exempt = true`, the buyer pays the **net** amount and the reason is recorded for the fiscal trail:

```php
$cart->setTaxContext($cart, [
    'country'       => 'IT',
    'exempt'        => true,
    'exempt_reason' => 'Export outside the EU',
]);
```

How the net is reached depends on whether your catalogue prices include tax — see [the relief mechanics below](#how-relief-reaches-the-net).

## B2B intra-EU reverse charge

For a VAT-registered customer in another EU member state, VAT is shifted to the buyer. When `commerce.tax.reverse_charge` is on **and** the context has `reverse_charge = true`, no VAT is charged by the seller and the `vat_number` is recorded:

```php
$cart->setTaxContext($cart, [
    'country'        => 'DE',
    'b2b'            => true,
    'vat_number'     => 'DE123456789',
    'reverse_charge' => true,
]);
```

::: callout warning "Eligibility is the host's job"
The package does not validate VAT numbers or decide who qualifies. The host determines eligibility — typically via a VIES check — and sets `reverse_charge` (and `vat_number`). If `commerce.tax.reverse_charge` is `false`, the flag is ignored and normal VAT applies.
:::

## How relief reaches the net

Both exemption and reverse charge are *relief*: the buyer must end up paying the net, tax-free amount. The way the calculator gets there depends on `commerce.tax.prices_include_tax`:

- **Exclusive prices** (`prices_include_tax = false`): the catalogue price is already net, so there is nothing to remove. A **zero, annotated** tax line records the `exempt_reason` / `vat_number`, and the total is unchanged.
- **Inclusive prices** (`prices_include_tax = true`, the default): the catalogue price embeds VAT, so the calculator **backs the embedded VAT out** of each line as a negative, total-affecting tax adjustment (carrying `relief: true` and the same annotation). A €122.00 gross line under 22% relief becomes a €100.00 charge — the buyer never pays VAT the breakdown says was not applied.

::: callout note "Why the tax total can be negative under inclusive relief"
Line subtotals are frozen from the catalogue (gross) price and never mutated, so the VAT removal shows up as a negative `tax_total` that reconciles the gross subtotal down to the net grand total (122.00 − 22.00 = 100.00). The adjustment is tagged `relief: true` so the breakdown stays explainable.
:::

See also: [Tax overview](/modules/tax/overview) · [Inclusive vs exclusive](/modules/tax/inclusive-exclusive) · [Multi-tenancy](/concepts/multi-tenancy) · [Pipeline](/concepts/pipeline).
