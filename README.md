# Laravel Commerce

[![Tests](https://github.com/sellinnate/laravel-commerce/actions/workflows/run-tests.yml/badge.svg)](https://github.com/sellinnate/laravel-commerce/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/sellinnate/laravel-commerce/actions/workflows/phpstan.yml/badge.svg)](https://github.com/sellinnate/laravel-commerce/actions/workflows/phpstan.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

A **headless, catalog-agnostic commerce domain engine for Laravel**: cart, order lifecycle, a
deterministic pricing pipeline, multi-tenancy, multi-currency, an immutable audit trail and ACL —
the transactional heart you build stores, checkouts, booking systems and quote flows on top of.

It is **not** a turnkey store. It owns the parts of commerce that are hard to get right and identical
across projects — money, order states, the calculation pipeline — and nothing else. Your catalogue
stays yours: anything implementing the `Purchasable` contract becomes sellable.

> 📚 **Full documentation:** <https://laravel-commerce.selli.io>

## Why

Every commercial app re-writes cart, totals, discounts, VAT and order states — and re-introduces the
same bugs (rounding, inclusive/exclusive tax, stock race conditions) project after project. Laravel
Commerce is **one engine, tested to the bone, reused across N projects**.

## Highlights

- **Catalog-agnostic** — binds to your models via the `Purchasable` contract; freezes an immutable
  order snapshot so history never changes when your catalogue does.
- **Headless & unopinionated** — no routes, controllers or templates imposed. Pure service layer + events.
- **Money correct by construction** — `brick/money` in minor units, never a float; centralised,
  per-currency rounding.
- **Deterministic calculation pipeline** — an explainable, line-by-line breakdown; reorderable and
  extensible without touching the core.
- **Order state machine** — `spatie/laravel-model-states`; illegal transitions are impossible by
  construction, authorised by policy and logged append-only.
- **Multi-tenant & multi-currency** — `tenant_id` + global scope on every table; one engine serves
  single-tenant and SaaS alike.
- **Audited** — every domain event persisted append-only; optional event sourcing for the Order
  aggregate.
- **Optional modules** — toggle each independently: **Pricing** (price books, coupons, promotions,
  gift cards), **Tax** (per-line jurisdictions, inclusive/exclusive, EU exemption & reverse charge)
  and **Inventory** (per-warehouse stock, TTL reservations, oversell prevention under a lock).

## Requirements

- PHP 8.3+ (8.3 / 8.4 / 8.5)
- Laravel 12 or 13

## Installation

```bash
composer require selli/commerce
php artisan vendor:publish --tag="commerce-migrations"
php artisan migrate
```

## Quick start

```php
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Actions\TransitionOrderState;
use Selli\Commerce\Order\States\Confirmed;

$carts = app(CartManager::class);

$cart = $carts->forOwner('user', (string) $user->id, 'EUR');
$carts->add($cart, $product, quantity: 2, options: ['size' => 'L']);

$calculation = $carts->calculate($cart);   // deterministic, explainable
$calculation->grandTotal();                 // Brick\Money\Money
$calculation->breakdown();                  // subtotal, discounts, tax, lines

$order = app(PlaceOrder::class)->handle($cart);   // transactional, emits OrderPlaced
$order->state;                                     // Pending

app(TransitionOrderState::class)->handle($order, Confirmed::class, by: $agent, reason: 'payment ok');
```

See the [Quick Start guide](https://laravel-commerce.selli.io/getting-started/quick-start) for the
full walkthrough, including implementing `Purchasable` on your model.

## Documentation

The documentation site is built with [docmd](https://github.com/mgks/docmd):

```bash
npm install
npm run docs:build   # outputs to ./site
npm run docs:dev     # local preview
```

## Testing

```bash
composer test            # Pest suite
composer test-coverage   # with 90% minimum coverage gate
composer analyse         # PHPStan (max level)
composer format          # Pint
```

## Roadmap

The core (cart, order, calculation pipeline, multi-tenancy, multi-currency, audit, ACL) and the
**Pricing & Promotions**, **Tax** and **Inventory** modules have shipped. Next on the roadmap:
Payments orchestration, Fulfillment and optional REST/GraphQL/Filament surfaces.

## License

The MIT License (MIT). See [License File](LICENSE.md).
