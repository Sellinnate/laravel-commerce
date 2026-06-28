---
title: "Architecture"
description: "Ports & adapters, a pure domain, and module boundaries."
type: concept
---

# Architecture

Laravel Commerce follows a **domain-driven, ports & adapters** design. The core is a pure domain —
aggregates, value objects, services — kept as independent of Eloquent and the framework as
practical. Persistence, HTTP and UI are adapters behind interfaces.

## Layers

Each area follows the same hexagonal anatomy:

```text
Domain/         contracts, value objects, calculation, enums, events  (framework-light)
Application/    actions / services / use-cases (CartManager, PlaceOrder, TransitionOrderState)
Infrastructure/ Eloquent models, repositories, casts, migrations
Support/        service provider, default implementations, helpers
```

The domain speaks only in **interfaces** — `CartRepository`, `OrderRepository`,
`PurchasableResolver`, `PriceResolver`, `RoundingStrategy`, `TenantContext`. Infrastructure
implements them. That is what lets you store the cart on the database in one project and swap the
implementation in another without touching the logic.

## The eight principles

1. **Domain-driven, ports & adapters** — a testable, replaceable core.
2. **Catalogue-agnostic** — binds via [`Purchasable`](/concepts/purchasable), freezes a snapshot.
3. **Headless & unopinionated** — no routes, controllers or templates imposed.
4. **Real modularity** — Pricing, Tax, Inventory are toggleable; absent modules leave no dead code.
5. **Money correct by construction** — never a float; always [`Money`](/concepts/money) in minor units.
6. **Deterministic calculation** — the total is a pure, repeatable, explainable function.
7. **Event-driven & traceable** — every state change emits a domain event; events are the audit base.
8. **Secure & isolated by default** — multi-tenancy and ACL are part of the contracts since v1.

## Module boundaries

The package ships as a single, modular package. Module boundaries are drawn as if they were separate
packages — isolated namespaces, no illicit cross-dependencies — so a future split to standalone
Packagist packages is mechanical, not a rewrite.

## Extension seams

Every decision that varies per client is an [extension point](/reference/contracts), never a core
edit: substitutable contracts, an open calculation pipeline, domain events, a redefinable state
machine, config flags and an extensible snapshot.
