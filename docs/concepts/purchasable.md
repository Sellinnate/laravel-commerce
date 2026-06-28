---
title: "Purchasable"
description: "The catalog-agnostic contract that lets any model be sold, and the hybrid live-binding plus immutable-snapshot model behind it."
type: concept
---

# Purchasable

`selli/commerce` is **catalog-agnostic**: it ships no product table. Anything in your application can be sold the moment it implements `Selli\Commerce\Contracts\Purchasable`. A physical SKU, a subscription plan, a service slot, a digital download, an event ticket — the engine neither knows nor cares.

## The contract

```php
namespace Selli\Commerce\Contracts;

use Brick\Money\Money;

interface Purchasable
{
    public function getPurchasableId(): string;
    public function getPurchasableType(): string;
    public function getName(): string;
    public function getUnitPrice(string $currency): Money;
    public function getPurchasableData(): array;
    public function isAvailable(int $quantity): bool;
}
```

| Method | Responsibility |
| --- | --- |
| `getPurchasableId()` | Stable identifier within the type (usually the primary key). |
| `getPurchasableType()` | A morph key string, e.g. `product`. |
| `getName()` | Human label captured onto the cart line. |
| `getUnitPrice($currency)` | A [`Money`](/concepts/money) value in the requested currency. |
| `getPurchasableData()` | Arbitrary array frozen into the order snapshot. |
| `isAvailable($quantity)` | Whether the requested quantity can be sold right now. |

## Live binding versus snapshot

The engine uses a **hybrid** model — and understanding it is the key to the whole package.

::: callout info "The 'paid 90, list now says 110' problem"
A customer adds an item at €90. While the cart is open, you raise the list price to €110. What should the cart show? What should an order placed last week show? These are two different questions and they need two different answers.
:::

- **Carts bind live.** A cart line stores a `purchasable_type` + `purchasable_id`. On [`recalculate()`](/concepts/cart) the engine re-resolves the live `Purchasable` through the [`PurchasableResolver`](/reference/contracts) and refreshes the unit price. An open cart always reflects current catalogue reality.
- **Orders snapshot.** When [`PlaceOrder`](/concepts/order) runs, each `OrderLine` freezes the name, unit price and the full `getPurchasableData()` array. The order is an immutable historical record: it says forever what was bought and what was paid, even if you later delete the product or change its price.

## Morph map registration

Because lines are stored polymorphically, register your types in a Laravel morph map so the stored `purchasable_type` is a stable string, not a class name:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap([
    'product' => \App\Models\Product::class,
    'plan'    => \App\Models\Plan::class,
]);
```

Return the same key from `getPurchasableType()`. This decouples your namespace from your data — you can move or rename classes without orphaning historical lines.

## A full implementation

```php
namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Contracts\Purchasable;

class Product extends Model implements Purchasable
{
    public function getPurchasableId(): string
    {
        return (string) $this->id;
    }

    public function getPurchasableType(): string
    {
        return 'product';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUnitPrice(string $currency): Money
    {
        // price_minor stored as an integer in minor units
        return Money::ofMinor($this->price_minor, $currency);
    }

    public function getPurchasableData(): array
    {
        return [
            'sku'   => $this->sku,
            'brand' => $this->brand,
        ];
    }

    public function isAvailable(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }
}
```

::: callout tip "Resolving back to the model"
The engine turns a stored `(type, id)` pair back into a live `Purchasable` via the [`PurchasableResolver`](/reference/contracts) contract. The default implementation uses the morph map; override the binding to source from an external PIM or API.
:::

See also: [Money](/concepts/money) · [Cart](/concepts/cart) · [Order](/concepts/order).
