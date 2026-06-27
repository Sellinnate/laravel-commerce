---
title: "Contracts Reference"
description: "Every contract in the engine, its methods, default implementation, and how to override it via config commerce.bindings."
---

# Contracts Reference

The engine is a set of interfaces with sensible defaults. Each contract lives in `Selli\Commerce\Contracts` (audit aside) and is swappable. This is the package's whole extensibility model: **bind your own implementation and the engine adopts it**.

## The contracts

| Contract | Methods | Default implementation |
| --- | --- | --- |
| `Purchasable` | `getPurchasableId()`, `getPurchasableType()`, `getName()`, `getUnitPrice($currency)`, `getPurchasableData()`, `isAvailable($qty)` | *Your models* — see [Purchasable](/concepts/purchasable). |
| `PurchasableResolver` | `resolve(string $type, string $id): ?Purchasable` | Morph-map resolver. |
| `CartRepository` | `find()`, `findActiveForOwner()`, `save()`, `delete()` | Driver from `cart.driver`. |
| `OrderRepository` | `find()`, `findByNumber()`, `save()` | Eloquent repository. |
| `OrderNumberGenerator` | `generate(?string $tenantId): string` | Prefixed/padded sequence (`order.*`). |
| `PriceResolver` | `resolve(Purchasable $p, string $currency, array $context = []): Money` | Reads `getUnitPrice()`. |
| `RoundingStrategy` | `round(Money): Money` | `DefaultRoundingStrategy` (HALF_UP). |
| `TenantContext` | `currentTenantId(): ?string`, `hasTenant(): bool` | `NullTenantContext`. |
| `ExchangeRateProvider` | `rate(string $from, string $to): BigNumber` | Bind your own FX source. |
| `Calculator` | `apply(Calculation): void`, `identifier(): string` | Pipeline calculators (`commerce.pipeline`). |

::: callout info "Resolvers, not magic"
`PurchasableResolver` turns a stored `(type, id)` pair back into a live `Purchasable` — the seam to source from a PIM or external API. `PriceResolver` centralises *how* a price is derived, letting you layer rules without touching models.
:::

## How to override

Two equivalent routes. The config map is declarative:

```php
// config/commerce.php
'bindings' => [
    \Selli\Commerce\Contracts\RoundingStrategy::class
        => \App\Commerce\BankersRounding::class,
],
```

Or bind directly in a service provider:

```php
public function register(): void
{
    $this->app->bind(
        \Selli\Commerce\Contracts\OrderNumberGenerator::class,
        \App\Commerce\InvoiceStyleNumbers::class,
    );
}
```

### A custom implementation

```php
namespace App\Commerce;

use Selli\Commerce\Contracts\OrderNumberGenerator;

class InvoiceStyleNumbers implements OrderNumberGenerator
{
    public function generate(?string $tenantId): string
    {
        return sprintf('INV-%s-%06d', now()->year, $this->nextSequence($tenantId));
    }

    private function nextSequence(?string $tenantId): int
    {
        // your sequence logic, optionally per tenant
    }
}
```

::: callout success "A custom binding always wins"
Whether registered via `commerce.bindings` or a direct container `bind`, your implementation takes precedence over the engine default. There is never a need to fork the package to change behaviour.
:::

See also: [Configuration](/reference/configuration) · [Multi-tenancy](/concepts/multi-tenancy) · [Pipeline](/concepts/pipeline).
