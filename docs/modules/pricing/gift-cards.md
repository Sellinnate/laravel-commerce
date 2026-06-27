---
title: "Gift Cards"
description: "Stored-balance instruments applied as a tender against the payable total — capped at their balance and the remaining due — with a row-locked balance decrement and an append-only ledger on placement."
---

# Gift Cards

A **gift card** is a stored-balance instrument. Unlike a [coupon](/modules/pricing/coupons) or a [promotion](/modules/pricing/promotions), it is not a discount — it is a **tender** that pays down the total, like cash. The `GiftCardCalculator` runs near the end of the [pipeline](/concepts/pipeline), after promotions, discounts and tax.

## The model

`Selli\Commerce\Pricing\Models\GiftCard`:

| Field | Notes |
| --- | --- |
| `tenant_id` | Scoped to the [tenant](/concepts/multi-tenancy). |
| `code` | Unique per tenant. |
| `initial_amount` | Integer, minor units — the value at issue. |
| `balance` | Integer, minor units — the value remaining. |
| `currency` | CHAR(3) ISO code. |
| `active` | Boolean on/off switch. |
| `expires_at` | Expiry timestamp. |

| Method | Returns |
| --- | --- |
| `balanceMoney()` | The remaining balance as `Brick\Money\Money`. |
| `isRedeemable(Carbon $at)` | Whether the card is active and unexpired at `$at`. |
| `transactions()` | The card's append-only transaction ledger. |

## Applying and removing

Drive gift cards through the [CartManager](/concepts/cart):

```php
use Selli\Commerce\Cart\CartManager;

$cart = app(CartManager::class);

$cart->applyGiftCard($cart, 'GIFT-7F3K');
$cart->giftCards($cart);            // → array of applied cards
$cart->removeGiftCard($cart, 'GIFT-7F3K');
```

Validation raises `GiftCardException` with a reason — `notFound`, `notRedeemable` or `currencyMismatch`:

```php
use Selli\Commerce\Exceptions\GiftCardException;

try {
    $cart->applyGiftCard($cart, 'GIFT-7F3K');
} catch (GiftCardException $e) {
    // notFound / notRedeemable / currencyMismatch
}
```

## Applied as a tender

The `GiftCardCalculator` applies each card against the **running payable total** — the amount left after promotions, discounts and tax. Each card pays up to its `balance` and never beyond the remaining total.

::: callout success "Capped both ways"
A card contributes the smaller of its balance and the amount still due. Because each tender is capped at the remaining total, the grand total can **never go negative**, however many cards are applied or however large their balances.
:::

When several cards are applied, they draw down the total in turn until either the cards or the balance due is exhausted. A card with balance left over after the total reaches zero simply keeps that balance.

## Redemption on placement

Applying a card to a cart does not touch its balance — that happens only when the order is placed. On `OrderPlaced`, for each redeemed card the engine:

1. takes a **row lock** on the card to serialise concurrent redemptions;
2. decrements `balance` by the redeemed amount;
3. writes an append-only `GiftCardTransaction` of type `Redeem`;
4. emits `GiftCardRedeemed`.

::: callout warning "Concurrency-safe"
The balance decrement runs under a row lock, so two simultaneous checkouts can never both spend the same funds. The transaction ledger is append-only, giving an auditable trail of every redemption.
:::

## Worked example

```php
use Selli\Commerce\Pricing\Models\GiftCard;

$card = GiftCard::create([
    'code'           => 'GIFT-7F3K',
    'initial_amount' => 5000,   // €50.00
    'balance'        => 5000,
    'currency'       => 'EUR',
    'active'         => true,
]);

$cart->applyGiftCard($cart, 'GIFT-7F3K');

// Cart total is €30.00 → the card tenders €30.00,
// the grand total becomes €0.00, and €20.00 stays on the card
// once the order is placed.
```

::: callout warning "Concurrency & reservation"
The tender shown on a cart is computed from the gift card's **live balance** at
calculation time. The actual debit happens once, atomically, when the order is
placed: it is taken under a row lock and **capped at the real remaining
balance**, so the ledger can never go negative and the card is never debited
twice for the same order (redemption is idempotent per order). If the *same*
card is applied to several carts that check out at almost the same moment, their
totals may each reflect more tender than the card ultimately holds — the money
ledger stays correct, but tender is honoured best-effort. Hard reservation of
gift-card balance (decrement on apply, release on abandonment) is planned for a
future release; the same applies to coupon usage limits, which are enforced at
application time.
:::

See also: [Cart](/concepts/cart) · [Money](/concepts/money) · [Pipeline](/concepts/pipeline) · [Audit & events](/concepts/audit-and-events).
