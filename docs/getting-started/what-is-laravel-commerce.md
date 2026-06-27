---
title: "What is Laravel Commerce?"
description: "The mental model: a headless commerce engine, not a store."
---

# What is Laravel Commerce?

Laravel Commerce is a **domain engine**, not a store. It owns the parts of commerce that are hard to
get right and identical across projects — and nothing else.

## What it is, and what it is not

| It is | It is not |
|---|---|
| A domain library (service layer + events) | A turnkey e-commerce platform |
| Agnostic to the host app's catalogue | A system with its own product models |
| Pure headless (no routes, no frontend, no admin) | A theme, an admin, or a public API |
| Extensible via contracts, pipeline and events | A monolith with hard-wired logic |
| Multi-tenant, multi-currency, audited, ACL-protected | A toy cart for prototypes |

## The mental model

1. **Your catalogue stays yours.** A model in your app implements the
   [`Purchasable`](/concepts/purchasable) contract. The engine never creates product tables.
2. **The cart is live.** It re-reads prices from your catalogue and is mutable until it converts.
3. **The order is stone.** On conversion the engine freezes an immutable snapshot — name, price,
   sku, tax and discount detail — so history never changes when your catalogue does.
4. **Totals are a pure function.** A deterministic [calculation pipeline](/concepts/pipeline)
   produces an explainable, line-by-line breakdown. Same input → same output.
5. **Everything emits events.** Domain events are the extension mechanism and the audit trail.

## When to use it

- You sell something from an app that already has its own catalogue (an ERP, a SaaS, a portal).
- You need correct money, tax and stock without re-implementing them.
- You want to own the frontend and integration, not adopt someone else's store.

## When not to use it

- You want a ready-made shop with admin and theme. Reach for a full platform instead.

Next: **[Installation](/getting-started/installation)**.
