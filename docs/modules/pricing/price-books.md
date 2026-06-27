---
title: "Price Books"
description: "Segment- and quantity-aware price lists resolved by a most-specific-wins rule: segment beats default, then priority, then quantity tier, then lowest amount — with a fallback to the purchasable's own price."
---

# Price Books

A **price book** is a list of prices that applies under certain conditions — a customer segment, a date window, a minimum quantity. When the Pricing module is enabled, the [`PriceBookResolver`](/modules/pricing/overview) becomes the active `PriceResolver`: every line's unit price is resolved through the books before the [pipeline](/concepts/pipeline) runs.

## The models

`Selli\Commerce\Pricing\Models\PriceBook`:

| Field | Notes |
| --- | --- |
| `tenant_id` | Scoped to the [tenant](/concepts/multi-tenancy). |
| `name` | Human label. |
| `currency` | CHAR(3) ISO code. |
| `segment` | Nullable — the customer segment this book targets. |
| `priority` | Integer; higher wins on a tie. |
| `starts_at` / `ends_at` | Validity window. |
| `active` | Boolean on/off switch. |

It has a `prices()` relation to `Selli\Commerce\Pricing\Models\Price`:

| Field | Notes |
| --- | --- |
| `price_book_id` | Owning book. |
| `purchasable_type` / `purchasable_id` | The [purchasable](/concepts/purchasable) this price covers. |
| `amount` | Integer, minor units. |
| `currency` | CHAR(3). |
| `min_quantity` | The quantity tier this price unlocks. |

`Price::toMoney()` returns a `Brick\Money\Money`.

## Resolution rules

The resolver collects every **valid** price for the purchasable, then picks the single most specific one in this order:

1. **Segment** — a price in the customer's exact segment beats one in the default (`null`) segment.
2. **Priority** — among equally specific books, the higher `priority` wins.
3. **Quantity tier** — the highest `min_quantity` the requested quantity still qualifies for.
4. **Lowest amount** — if everything else ties, the cheapest price wins.

::: callout info "Validity"
A price is valid only when its book is `active` **and** now falls within `starts_at`/`ends_at`. Expired, future, or inactive books are ignored entirely.
:::

The resolution context may carry a `segment` (string) and a `quantity` (int). When no segment is supplied, the default comes from `config('commerce.pricing.default_segment')` (default `'default'`).

::: callout warning "Fallback"
If no book yields a valid price for the purchasable, the resolver falls back to the purchasable's own `getUnitPrice()`. A catalogue item is always sellable, with or without a book.
:::

## Worked example

Create a book, add a price, then watch a cart pick it up:

```php
use Selli\Commerce\Pricing\Models\PriceBook;
use Selli\Commerce\Cart\CartManager;

$book = PriceBook::create([
    'name'     => 'EU retail',
    'currency' => 'EUR',
    'segment'  => null,        // the default segment
    'priority' => 10,
    'active'   => true,
]);

$book->prices()->create([
    'purchasable_type' => 'product',
    'purchasable_id'   => $product->id,
    'amount'           => 800,     // €8.00 in minor units
    'currency'         => 'EUR',
    'min_quantity'     => 1,
]);

$cart = app(CartManager::class)->forOwner('user', (string) $user->id, 'EUR');
$cart->add($cart, $product, quantity: 1);

$cart->calculate($cart)->itemsSubtotal(); // → EUR 8.00
```

## Quantity tiers

Add several `Price` rows to the same book with rising `min_quantity` values to express volume breaks. A line of quantity 10 resolves to the highest tier whose `min_quantity` it meets:

```php
$book->prices()->create([...'amount' => 800, 'min_quantity' => 1]);   // 1+  → €8.00
$book->prices()->create([...'amount' => 700, 'min_quantity' => 5]);   // 5+  → €7.00
$book->prices()->create([...'amount' => 600, 'min_quantity' => 10]);  // 10+ → €6.00
```

## Segments

Give a book a non-null `segment` and pass that segment in the resolution context to target it. A matching segment always beats the default book, regardless of priority — specificity comes first. Use segments for wholesale tiers, member pricing, or regional lists.

See also: [Purchasable](/concepts/purchasable) · [Money](/concepts/money) · [Coupons](/modules/pricing/coupons) · [Pipeline](/concepts/pipeline).
