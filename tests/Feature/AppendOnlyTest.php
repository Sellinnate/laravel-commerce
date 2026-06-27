<?php

declare(strict_types=1);

use Selli\Commerce\Audit\Models\DomainEvent;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Exceptions\ImmutableRecordException;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Tests\Fixtures\Product;

function placedOrder(): Order
{
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);

    return app(PlaceOrder::class)->handle($cart);
}

it('forbids updating a domain event', function (): void {
    placedOrder();
    $event = DomainEvent::query()->firstOrFail();

    $event->name = 'Tampered';
    $event->save();
})->throws(ImmutableRecordException::class);

it('forbids deleting a domain event', function (): void {
    placedOrder();
    DomainEvent::query()->firstOrFail()->delete();
})->throws(ImmutableRecordException::class);

it('forbids updating an order state transition', function (): void {
    $order = placedOrder();
    $transition = $order->transitions()->firstOrFail();

    $transition->reason = 'Tampered';
    $transition->save();
})->throws(ImmutableRecordException::class);
