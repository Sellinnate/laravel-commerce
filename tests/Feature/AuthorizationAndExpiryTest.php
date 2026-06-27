<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Gate;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Actions\TransitionOrderState;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\Policies\OrderPolicy;
use Selli\Commerce\Order\States\Confirmed;
use Selli\Commerce\Tests\Fixtures\Product;

function placeSimpleOrder(): Order
{
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);

    return app(PlaceOrder::class)->handle($cart);
}

it('authorises transitions even without an actor via the policy', function (): void {
    // Tighten the policy to deny all transitions.
    $denyAll = new class extends OrderPolicy
    {
        public function transition(?Authorizable $user, Order $order, string $toState): bool
        {
            return false;
        }
    };
    Gate::policy(Order::class, $denyAll::class);

    $order = placeSimpleOrder();

    app(TransitionOrderState::class)->handle($order, Confirmed::class);
})->throws(AuthorizationException::class);

it('allows transitions under the default permissive policy', function (): void {
    $order = placeSimpleOrder();

    app(TransitionOrderState::class)->handle($order, Confirmed::class, reason: 'ok');

    expect($order->state)->toBeInstanceOf(Confirmed::class);
});

it('does not resurrect an expired cart for an owner', function (): void {
    $carts = app(CartManager::class);
    $cart = $carts->forOwner('customer', 'cust-1', 'EUR');

    // Force the cart to be expired in the past.
    $cart->forceFill(['expires_at' => now()->subDay()])->save();

    $fresh = $carts->forOwner('customer', 'cust-1', 'EUR');

    expect($fresh->id)->not->toBe($cart->id);
});
