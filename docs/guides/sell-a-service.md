---
title: "Recipe: Sell a Service"
description: "Make a non-physical Plan model purchasable, build a cart and place an order — no Inventory module required."
---

# Recipe: Sell a Service

Not everything sold is a boxed product. This recipe sells a **service plan** — digital, unlimited, always available — to show how catalogue-agnostic the engine is. Because supply is unlimited, there is nothing to reserve and no Inventory module to wait for.

## 1. Make the plan purchasable

Implement [`Purchasable`](/concepts/purchasable) on your existing model. The only thing that makes it a "service" is `isAvailable()` returning `true` unconditionally:

```php
namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Contracts\Purchasable;

class Plan extends Model implements Purchasable
{
    public function getPurchasableId(): string
    {
        return (string) $this->id;
    }

    public function getPurchasableType(): string
    {
        return 'plan';
    }

    public function getName(): string
    {
        return $this->name; // e.g. "Pro — Monthly"
    }

    public function getUnitPrice(string $currency): Money
    {
        return Money::ofMinor($this->price_minor, $currency);
    }

    public function getPurchasableData(): array
    {
        return [
            'interval' => $this->interval, // 'monthly'
            'tier'     => $this->tier,      // 'pro'
        ];
    }

    public function isAvailable(int $quantity): bool
    {
        return true; // digital + unlimited: always sellable
    }
}
```

::: callout info "No Inventory module needed"
Inventory is a [planned module](/concepts/pipeline) for stock-tracked goods. A digital service has no stock, so you simply return `true` from `isAvailable()` and skip it entirely.
:::

## 2. Register the morph key

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap([
    'plan' => \App\Models\Plan::class,
]);
```

## 3. Build a cart

```php
use Selli\Commerce\Cart\CartManager;

$carts = app(CartManager::class);

$cart = $carts->forOwner('user', (string) $user->id, 'EUR');
$carts->add($cart, $plan, quantity: 1);

$calc = $carts->calculate($cart);
$calc->grandTotal(); // Brick\Money\Money
```

## 4. Place the order

```php
use Selli\Commerce\Order\Actions\PlaceOrder;

$order = app(PlaceOrder::class)->handle($cart, [
    'customer_type' => 'user',
    'customer_id'   => (string) $user->id,
]);

$order->number;     // generated order number
$order->grand_total; // Money snapshot
```

`PlaceOrder` runs the final [calculation](/concepts/pipeline), freezes the line snapshot (including your `interval`/`tier` data), persists the order, marks the cart `Converted`, and emits `OrderPlaced`. Listen for that event to provision access:

```php
use Selli\Commerce\Events\Order\OrderPlaced;

Event::listen(OrderPlaced::class, function (OrderPlaced $event) {
    // grant the subscription, send the welcome email…
});
```

::: callout success "That's the whole loop"
Implement the contract, register the morph key, add to a cart, place the order. The engine treated your service exactly like any other purchasable — because to the engine, it is.
:::

See also: [Purchasable](/concepts/purchasable) · [Cart](/concepts/cart) · [place-and-transition](/guides/place-and-transition).
