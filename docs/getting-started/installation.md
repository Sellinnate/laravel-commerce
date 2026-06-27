---
title: "Installation"
description: "Install selli/commerce via Composer and publish its config and migrations."
---

# Installation

## Requirements

- PHP **8.3+** (8.3 / 8.4 / 8.5)
- Laravel **12** or **13**

## Install via Composer

```bash
composer require selli/commerce
```

The service provider is auto-discovered — no manual registration.

## Publish the migrations

```bash
php artisan vendor:publish --tag="commerce-migrations"
php artisan migrate
```

This creates the core tables (`commerce_carts`, `commerce_cart_items`, `commerce_orders`,
`commerce_order_lines`, `commerce_order_state_transitions`, `commerce_domain_events`). The table
prefix is configurable — see [Configuration](/reference/configuration).

## Publish the config (optional)

```bash
php artisan vendor:publish --tag="commerce-config"
```

This writes `config/commerce.php`. Every option ships with a sensible default, so you can start with
**zero configuration**.

## Optional dependencies

The heavy dependencies are `suggest`, installed only when you opt into the matching feature:

```bash
# Event sourcing for the Order aggregate (Audit level 2)
composer require spatie/laravel-event-sourcing

# Granular role/permission ACL
composer require spatie/laravel-permission
```

::: callout tip
The core needs none of these. `brick/money` and `spatie/laravel-model-states` are required and pulled
in automatically.
:::

Next: **[Quick Start](/getting-started/quick-start)**.
