---
title: "Access Control"
description: "Every sensitive action passes through a Laravel policy — permissive by default for headless apps, overridable, and combined with tenant isolation."
type: concept
---

# Access Control

Authorisation in `selli/commerce` is **standard Laravel policies**, nothing exotic. Every sensitive action routes through a policy, the defaults are permissive so headless apps work out of the box, and tenancy already isolates rows so you start from a safe baseline.

## Policies guard sensitive actions

The engine's actions consult Laravel's authorisation layer before mutating anything sensitive. `Selli\Commerce\Order\Policies\OrderPolicy` covers the order surface:

| Ability | Default |
| --- | --- |
| `view` | `true` |
| `transition` | `true` |
| `refund` | `true` |
| `applyManualDiscount` | `true` |

## Permissive by default

::: callout info "Why default-allow?"
A headless engine cannot know your roles, guards or org chart. Shipping locked-down defaults would break every integrator on day one. So `OrderPolicy` returns `true` everywhere — the engine works immediately — and you **tighten** it deliberately for your domain.
:::

## Overriding the policy

Bind your own policy, or register it the usual Laravel way:

```php
use Illuminate\Support\Facades\Gate;
use Selli\Commerce\Order\Models\Order;

Gate::policy(Order::class, \App\Policies\OrderPolicy::class);
```

```php
class OrderPolicy
{
    public function transition(User $user, Order $order): bool
    {
        return $user->hasRole('fulfilment');
    }

    public function refund(User $user, Order $order): bool
    {
        return $user->can('orders.refund');
    }
}
```

### spatie/laravel-permission adapter

Because abilities are plain policy methods, delegating to `spatie/laravel-permission` is a one-liner per ability — call `$user->can('...')` / `$user->hasPermissionTo('...')` inside the policy. No engine changes required.

## The transition double-check

State changes are the most safety-critical action, so [`TransitionOrderState`](/concepts/order) enforces **two independent gates**:

1. **Legality** — the state machine. Is `processing → completed` a permitted edge? Illegal moves throw `Spatie\ModelStates\Exceptions\TransitionNotFound`.
2. **Permission** — the policy. When an `Authorizable` actor is passed as `$by`, the `transition` ability must allow it.

```php
app(TransitionOrderState::class)->handle($order, 'completed', by: $staff);
// passes only if the edge is legal AND $staff is authorised
```

Both must pass. A legal transition by an unauthorised actor is refused; an authorised actor still cannot make an illegal jump. See the [place-and-transition guide](/guides/place-and-transition) for handling each failure.

## Combined with tenancy

Authorisation composes with [multi-tenancy](/concepts/multi-tenancy). The global `TenantScope` filters every query by the current tenant, so a manager of tenant A cannot even **load** tenant B's orders — let alone act on them. Tenancy provides isolation by construction; policies provide intent-level permission **within** a tenant. The two layers are independent and additive.

::: callout success "Safe baseline, deliberate tightening"
Out of the box: tenants are isolated, actions are auditable, policies allow. You harden by overriding policies for your roles — never by patching the engine.
:::

See also: [Order](/concepts/order) · [Multi-tenancy](/concepts/multi-tenancy) · [Contracts](/reference/contracts).
