<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Actions\TransitionOrderState;
use Selli\Commerce\Order\Models\OrderLine;
use Selli\Commerce\Order\States\Cancelled;
use Selli\Commerce\Order\States\Confirmed;
use Selli\Commerce\Tests\Fixtures\Customer;
use Selli\Commerce\Tests\Fixtures\Product;

it('attributes a transition to an authorised actor', function (): void {
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);
    $order = app(PlaceOrder::class)->handle($cart);

    $agent = Customer::create(['name' => 'Agent']);
    app(TransitionOrderState::class)->handle($order, Confirmed::class, by: $agent, reason: 'payment ok');

    $transition = $order->transitions()->where('to_state', 'confirmed')->first();

    expect($order->state)->toBeInstanceOf(Confirmed::class)
        ->and($transition->actor_id)->toBe((string) $agent->getKey())
        ->and($transition->reason)->toBe('payment ok');
});

it('can cancel a pending order', function (): void {
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);
    $order = app(PlaceOrder::class)->handle($cart);

    app(TransitionOrderState::class)->handle($order, Cancelled::class, reason: 'out of stock');

    expect($order->state)->toBeInstanceOf(Cancelled::class)
        ->and($order->state->isFinal())->toBeTrue();
});

it('snapshots line totals and belongs to its order', function (): void {
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'sku' => 'SKU1', 'price_cents' => 1250]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 2);
    $order = app(PlaceOrder::class)->handle($cart);

    /** @var OrderLine $line */
    $line = $order->lines->first();

    expect($line->line_subtotal->getMinorAmount()->toInt())->toBe(2500)
        ->and($line->line_total->getMinorAmount()->toInt())->toBe(2500)
        ->and($line->sku)->toBe('SKU1')
        ->and($line->order->is($order))->toBeTrue();
});
