---
title: "Configuration Reference"
description: "Every key in config/commerce.php — defaults, accepted values and meaning."
type: reference
---

# Configuration Reference

All behaviour is driven by `config/commerce.php`. Publish it, then tune the keys below. Every section is annotated with its default and effect.

## Core

| Key | Default | Meaning |
| --- | --- | --- |
| `default_currency` | `'EUR'` | Currency used when a cart is created without one. |
| `table_prefix` | `'commerce_'` | Prefix for every engine table. See [schema](/reference/database-schema). |

```php
'default_currency' => 'EUR',
'table_prefix'     => 'commerce_',
```

## Modules

Feature flags for the optional modules. Each is independent: a disabled module registers no calculators and leaves no dead code path in the [pipeline](/concepts/pipeline).

| Key | Default | Meaning |
| --- | --- | --- |
| `modules.pricing` | `true` | [Price books, coupons, promotions, gift cards](/modules/pricing/overview). |
| `modules.tax` | `true` | [Per-line tax with jurisdictions and relief](/modules/tax/overview). |
| `modules.inventory` | `true` | [Stock, reservations and oversell prevention](/modules/inventory/overview). |

## Cart

| Key | Default | Meaning |
| --- | --- | --- |
| `cart.driver` | `'database'` | Backing store for the `CartRepository`. |
| `cart.cache_store` | `null` | Cache store name when using a cache driver. |
| `cart.ttl` | — | Lifetime before a cart abandons/expires. |
| `cart.merge_strategy` | `KeepHighestQuantity` | Default [MergeStrategy](/concepts/cart). |
| `cart.idempotent_add` | `true` | Re-adding the same purchasable+options increments the line. |

```php
'cart' => [
    'driver'         => 'database',
    'cache_store'    => null,
    'ttl'            => 60 * 24 * 7, // minutes
    'merge_strategy' => \Selli\Commerce\Enums\MergeStrategy::KeepHighestQuantity,
    'idempotent_add' => true,
],
```

## Inventory

Governs the [Inventory module](/modules/inventory/overview) (when `modules.inventory` is on).

| Key | Default | Meaning |
| --- | --- | --- |
| `inventory.default_warehouse` | `'default'` | Code of the warehouse used by automatic operations; auto-created on first use. |
| `inventory.reserve_on` | `'place_order'` | When stock is held: `place_order` or `add_to_cart`. |
| `inventory.reservation_ttl` | `60` | Minutes a cart hold lives before it lapses (`null` = never). |
| `inventory.backorder` | `'deny'` | `deny` throws `InsufficientStockException`; `allow` permits selling below zero and annotates the order. |

```php
'inventory' => [
    'default_warehouse' => 'default',
    'reserve_on'        => 'place_order',
    'reservation_ttl'   => 60,
    'backorder'         => 'deny',
],
```

## Pipeline

An **ordered** array of `Calculator` class names. Order is the maths — see [the pipeline](/concepts/pipeline). `GrandTotalCalculator` must remain last.

```php
'pipeline' => [
    \Selli\Commerce\Calculation\Calculators\SubtotalCalculator::class,
    // your discount / tax / shipping / fee calculators…
    \Selli\Commerce\Calculation\Calculators\GrandTotalCalculator::class,
],
```

## Rounding

| Key | Default | Meaning |
| --- | --- | --- |
| `rounding.mode` | `HALF_UP` | Mode for the centralised, currency-aware [RoundingStrategy](/concepts/money). |

```php
'rounding' => [
    'mode' => \Brick\Math\RoundingMode::HALF_UP,
],
```

## Tenancy

| Key | Default | Meaning |
| --- | --- | --- |
| `tenancy.mode` | `'null'` | `'null'` (single tenant) or `'callback'`. |
| `tenancy.resolver` | `null` | Closure returning the tenant id when `mode` is `callback`. |

```php
'tenancy' => [
    'mode'     => 'null',
    'resolver' => null,
],
```

See [Multi-tenancy](/concepts/multi-tenancy). A custom `TenantContext` binding overrides this.

## Audit

| Key | Default | Meaning |
| --- | --- | --- |
| `audit.record_domain_events` | `true` | Level 1: persist every Recordable event to `domain_events`. |
| `audit.event_sourcing` | `false` | Level 2: event-source the Order aggregate. |

```php
'audit' => [
    'record_domain_events' => true,
    'event_sourcing'       => false,
],
```

See [Audit & Events](/concepts/audit-and-events).

## Order numbering

| Key | Default | Meaning |
| --- | --- | --- |
| `order.number_prefix` | — | String prefixed to generated order numbers. |
| `order.number_pad` | — | Zero-pad width for the numeric portion. |

```php
'order' => [
    'number_prefix' => 'ORD-',
    'number_pad'    => 6,
],
```

## Bindings

A map of contract ⇒ implementation, the supported way to swap any engine service. A binding here (or a direct container binding) always wins. See [Contracts](/reference/contracts).

```php
'bindings' => [
    \Selli\Commerce\Contracts\RoundingStrategy::class => \App\Commerce\BankersRounding::class,
    \Selli\Commerce\Contracts\OrderNumberGenerator::class => \App\Commerce\MyNumbers::class,
],
```

See also: [Contracts](/reference/contracts) · [Database schema](/reference/database-schema).
