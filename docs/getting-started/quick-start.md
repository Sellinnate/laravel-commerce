---
title: "Quick Start"
description: "From a purchasable model to a placed order in five steps."
type: guide
---

# Quick Start

## 1. Make your model purchasable

Implement the [`Purchasable`](/concepts/purchasable) contract on any catalogue model:

```php
use Brick\Money\Money;
use Selli\Commerce\Contracts\Purchasable;

class Product extends Model implements Purchasable
{
    public function getPurchasableId(): string   { return (string) $this->id; }
    public function getPurchasableType(): string { return 'product'; }
    public function getName(): string            { return $this->name; }

    public function getUnitPrice(string $currency): Money
    {
        return Money::ofMinor($this->price_cents, $currency);
    }

    public function getPurchasableData(): array  { return ['sku' => $this->sku]; }
    public function isAvailable(int $quantity): bool { return $this->stock >= $quantity; }
}
```

Register the morph alias in a service provider:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap(['product' => \App\Models\Product::class]);
```

## 2. Build a cart

```php
use Selli\Commerce\Cart\CartManager;

$carts = app(CartManager::class);

$cart = $carts->forOwner('user', (string) $user->id, 'EUR');
$carts->add($cart, $product, quantity: 2, options: ['size' => 'L']);
```

## 3. Read the total — explainable line by line

```php
$calculation = $carts->calculate($cart);

$calculation->grandTotal();   // Brick\Money\Money
$calculation->breakdown();    // subtotal, discounts, tax, lines, adjustments
```

## 4. Convert the cart into an order

```php
use Selli\Commerce\Order\Actions\PlaceOrder;

$order = app(PlaceOrder::class)->handle($cart);   // emits OrderPlaced, in one DB transaction

$order->number;       // e.g. "ORD-000001"
$order->state;        // Pending
$order->grand_total;  // frozen Money
```

## 5. Transition the order through its lifecycle

```php
use Selli\Commerce\Order\Actions\TransitionOrderState;
use Selli\Commerce\Order\States\Confirmed;

app(TransitionOrderState::class)->handle($order, Confirmed::class, by: $agent, reason: 'payment ok');
```

Every transition is validated by the [state machine](/concepts/order), authorised by a
[policy](/concepts/acl), logged append-only and emits an event.

::: callout success "That's the whole loop"
Your app never computes VAT, discounts or stock itself — it asks the engine and receives correct,
traceable, reconstructable numbers.
:::
