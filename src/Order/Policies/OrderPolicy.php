<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Selli\Commerce\Order\Models\Order;

/**
 * Default, permissive order policy so headless apps work out of the box.
 * Integrators override the binding (or use spatie/laravel-permission) to
 * enforce real authorisation. Tenancy already isolates rows by construction;
 * these methods govern *actions* on a visible order.
 */
class OrderPolicy
{
    public function view(?Authorizable $user, Order $order): bool
    {
        return true;
    }

    /**
     * @param  class-string  $toState
     */
    public function transition(?Authorizable $user, Order $order, string $toState): bool
    {
        return true;
    }

    public function refund(?Authorizable $user, Order $order): bool
    {
        return true;
    }

    public function applyManualDiscount(?Authorizable $user, Order $order): bool
    {
        return true;
    }
}
