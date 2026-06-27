---
title: "Database Schema"
description: "Table-by-table reference for the engine's tables — columns, types, ULID keys, tenant scoping, money pairs, append-only tables and soft deletes."
---

# Database Schema

Every engine table shares the same conventions:

- **ULID primary keys** (`id`, `CHAR(26)`) — sortable and globally unique.
- An indexed **`tenant_id`** on every domain table, scoped automatically by the [TenantScope](/concepts/multi-tenancy).
- **Money as a pair**: `{name}_amount` (`BIGINT`, minor units) + `{name}_currency` (`CHAR(3)`), via [MoneyCast](/concepts/money).
- **JSON columns** for snapshots, addresses, metadata and breakdowns.
- A **configurable prefix** (`commerce_` by default, via `table_prefix`).

Names below omit the prefix.

## carts

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(26) | ULID PK. |
| `tenant_id` | string, indexed | Nullable when single-tenant. |
| `owner_type` / `owner_id` | string, nullable | Polymorphic owner. |
| `currency` | CHAR(3) | Fixed for the cart's life. |
| `status` | string | `CartStatus` enum. |
| `metadata` | JSON | Free-form. |
| `expires_at` | timestamp, nullable | Drives abandon/expire. |
| `created_at` / `updated_at` | timestamps | |

## cart_items

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(26) | ULID PK. |
| `cart_id` | CHAR(26), indexed | FK to `carts`. |
| `purchasable_type` / `purchasable_id` | string | Live binding (morph). |
| `name` | string | Captured label. |
| `quantity` | integer | |
| `unit_price_amount` / `unit_price_currency` | BIGINT / CHAR(3) | Money pair. |
| `options` | JSON | Part of line identity. |
| `metadata` | JSON | Free-form. |
| `created_at` / `updated_at` | timestamps | |

## orders

Soft-deleted — orders are never physically removed.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(26) | ULID PK. |
| `tenant_id` | string, indexed | |
| `number` | string, indexed | Human-facing order number. |
| `customer_type` / `customer_id` | string | Polymorphic customer. |
| `currency` | CHAR(3) | |
| `state` | string | State machine value. |
| `subtotal_amount` / `subtotal_currency` | BIGINT / CHAR(3) | Money pair. |
| `discount_total_amount` / `_currency` | BIGINT / CHAR(3) | Money pair. |
| `tax_total_amount` / `_currency` | BIGINT / CHAR(3) | Money pair. |
| `shipping_total_amount` / `_currency` | BIGINT / CHAR(3) | Money pair. |
| `grand_total_amount` / `_currency` | BIGINT / CHAR(3) | Money pair. |
| `billing_address` / `shipping_address` | JSON | Snapshots. |
| `metadata` | JSON | Free-form. |
| `placed_at` | timestamp | |
| `created_at` / `updated_at` / `deleted_at` | timestamps | Soft delete. |

## order_lines

Immutable snapshots — see [Order](/concepts/order).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(26) | ULID PK. |
| `order_id` | CHAR(26), indexed | FK to `orders`. |
| `purchasable_type` / `purchasable_id` | string | Snapshot reference. |
| `name` | string | Frozen at placement. |
| `sku` | string, nullable | |
| `quantity` | integer | |
| `unit_price_*` | BIGINT / CHAR(3) | Money pair. |
| `line_subtotal_*` | BIGINT / CHAR(3) | Money pair. |
| `discount_total_*` | BIGINT / CHAR(3) | Money pair. |
| `tax_total_*` | BIGINT / CHAR(3) | Money pair. |
| `line_total_*` | BIGINT / CHAR(3) | Money pair. |
| `snapshot` | JSON | Full `getPurchasableData()`. |
| `tax_detail` | JSON | |
| `discount_detail` | JSON | |

## order_state_transitions

**Append-only** — no updates or deletes.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(26) | ULID PK. |
| `tenant_id` | string, indexed | |
| `order_id` | CHAR(26), indexed | FK to `orders`. |
| `from_state` / `to_state` | string | |
| `actor_type` / `actor_id` | string, nullable | Who made the change. |
| `reason` | string, nullable | |
| `created_at` | timestamp | No `updated_at`. |

## domain_events

**Append-only** — the [level-1 audit trail](/concepts/audit-and-events).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(26) | ULID PK. |
| `tenant_id` | string, indexed | |
| `name` | string | Event name. |
| `subject_type` / `subject_id` | string | Cart or order concerned. |
| `payload` | JSON | Event data. |
| `actor_type` / `actor_id` | string, nullable | Who triggered it. |
| `created_at` | timestamp | No `updated_at`. |

::: callout warning "Append-only means append-only"
`order_state_transitions` and `domain_events` are written once and never mutated. Treat them as an immutable ledger — that property is what makes the audit trail trustworthy.
:::

See also: [Money](/concepts/money) · [Multi-tenancy](/concepts/multi-tenancy) · [Configuration](/reference/configuration).
