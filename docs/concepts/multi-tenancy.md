---
title: "Multi-tenancy"
description: "Tenancy-agnostic isolation via a TenantContext seam, a global scope that filters and auto-stamps every domain row, and zero cost when single-tenant."
---

# Multi-tenancy

`selli/commerce` is **tenancy-agnostic**. It neither ships nor assumes a tenancy package; instead it exposes a single seam — `TenantContext` — that any tenancy strategy can plug into. Single-tenant apps pay nothing; multi-tenant apps get isolation by construction.

## The TenantContext seam

```php
namespace Selli\Commerce\Contracts;

interface TenantContext
{
    public function currentTenantId(): ?string;
    public function hasTenant(): bool;
}
```

The engine asks this one question — *"which tenant are we acting for?"* — and never cares how the answer is produced.

### NullTenantContext (default)

The default binding. `currentTenantId()` returns `null`, `hasTenant()` is `false`. There is **no query overhead and no tenant column filtering** — perfect for the single-tenant majority. You opt into tenancy only when you need it.

### CallbackTenantContext

Resolves the tenant id from a closure, making it the adapter point for `stancl/tenancy`, `spatie/laravel-multitenancy`, or anything else:

```php
// config/commerce.php
'tenancy' => [
    'mode'     => 'callback',
    'resolver' => fn () => tenant()?->getKey(), // stancl/tenancy
],
```

Any package that knows the current tenant — a subdomain resolver, a path segment, a JWT claim — can feed the closure.

## Global scope and auto-stamping

Every domain table carries an indexed `tenant_id`. A global `TenantScope` is applied to all domain models:

- **Reads** are filtered to `currentTenantId()` automatically — a query for tenant A can never see tenant B's rows.
- **Writes** are auto-stamped with the current tenant id, so you never set it by hand.

```php
// With a tenant active, this only ever returns the current tenant's carts:
Cart::all();

// New rows are stamped automatically:
$cart = app(CartManager::class)->create('EUR'); // tenant_id set for you
```

::: callout success "Isolation by construction"
Because the scope filters reads and stamps writes, cross-tenant access is not a permission you grant or forget — it is structurally impossible. A manager of tenant A cannot reach tenant B's orders even if they hold the order's id. This underpins the [ACL](/concepts/acl) model.
:::

When `NullTenantContext` is active the scope is a no-op, so single-tenant deployments see no behavioural change and no cost.

## Configuration

| Key | Values | Meaning |
| --- | --- | --- |
| `tenancy.mode` | `'null'` \| `'callback'` | Which built-in context to use. |
| `tenancy.resolver` | closure | Used when `mode` is `callback`. |

```php
'tenancy' => [
    'mode'     => 'null',
    'resolver' => null,
],
```

## A custom binding always wins

The `mode`/`resolver` config is a convenience. If you need bespoke logic — a context that reads from a request, a queue payload, or your own tenancy service — bind your own `TenantContext` implementation:

```php
$this->app->bind(
    \Selli\Commerce\Contracts\TenantContext::class,
    \App\Commerce\AcmeTenantContext::class,
);
```

A custom container binding **always takes precedence** over the config-driven default. This is the same override mechanism used across the engine — see [Contracts](/reference/contracts).

See also: [Configuration](/reference/configuration) · [ACL](/concepts/acl) · [Database schema](/reference/database-schema).
