---
title: "Laravel Commerce"
description: "A headless, catalog-agnostic commerce domain engine for Laravel."
type: concept
---

# Laravel Commerce

**The transactional heart for any Laravel app that sells something.** Laravel Commerce is a
headless, catalog-agnostic domain engine: it provides the **cart** and the **order lifecycle** as a
reusable library that drops into any application — a management system, a SaaS, a marketplace, a
quote configurator — without imposing a catalogue, a frontend or a payment channel.

It is **not** a turnkey store. It is the engine you build stores, checkouts, booking systems and
quote flows on top of — anything that ends in *"someone buys something"*.

::: callout tip "New here? Start at zero."
Read **[What is Laravel Commerce?](/getting-started/what-is-laravel-commerce)** for the mental model,
then **[Installation](/getting-started/installation)** → **[Quick Start](/getting-started/quick-start)**.
:::

## Why it exists

Every commercial app re-writes the same logic — cart, totals, discounts, VAT, order states — and
re-introduces the same class of bugs (rounding, inclusive/exclusive tax, stock race conditions)
project after project. Laravel Commerce is **one engine, tested to the bone, reused across N
projects**: it compresses time-to-market and eliminates the money-and-state bug class.

## What makes it different

::: card "Catalog-agnostic by design"
Every other Laravel commerce solution ships its own product model and asks you to adopt it. Laravel
Commerce assumes the opposite: **the catalogue belongs to the host app**. Anything that implements
the [`Purchasable`](/concepts/purchasable) contract becomes sellable — `Product`, `Plan`, `Service`,
`Room`, `TicketType` — and the engine orchestrates cart, calculation and order around it.
:::

## How these docs are organised

- **[Getting Started](/getting-started/what-is-laravel-commerce)** — from zero to your first order.
- **[Concepts](/concepts/architecture)** — *how it works and why*: the Purchasable contract,
  money, the cart, the order state machine, the calculation pipeline, multi-tenancy, audit and ACL.
- **[Guides](/guides/sell-a-service)** — task-focused recipes you can copy-paste.
- **[Reference](/reference/configuration)** — config, contracts, events and the database schema.

## Built on solid foundations

| Concern | Choice |
|---|---|
| Money | [`brick/money`](https://github.com/brick/money) — immutable, exact, multi-currency. Never a float. |
| Order states | [`spatie/laravel-model-states`](https://github.com/spatie/laravel-model-states) — illegal transitions impossible by construction. |
| IDs | ULID — sortable and safe to expose. |
| Tests | Pest — unit, feature and contract tests, 90%+ coverage. |
| Static analysis | Larastan / PHPStan at max level, Pint in CI. |
