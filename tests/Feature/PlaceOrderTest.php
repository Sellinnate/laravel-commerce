<?php

declare(strict_types=1);

use Selli\Commerce\Audit\Models\DomainEvent;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Enums\CartStatus;
use Selli\Commerce\Exceptions\EmptyCartException;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Actions\TransitionOrderState;
use Selli\Commerce\Order\States\Completed;
use Selli\Commerce\Order\States\Confirmed;
use Selli\Commerce\Order\States\Pending;
use Selli\Commerce\Order\States\Processing;
use Selli\Commerce\Tests\Fixtures\Product;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
    $this->place = app(PlaceOrder::class);
});

it('converts a cart into an order with frozen snapshot', function (): void {
    $product = Product::create(['name' => 'Widget', 'sku' => 'ABC', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 3);

    $order = $this->place->handle($cart);

    expect($order->number)->toStartWith('ORD-')
        ->and($order->grand_total->getMinorAmount()->toInt())->toBe(3000)
        ->and($order->lines)->toHaveCount(1)
        ->and($order->lines->first()->snapshot['sku'])->toBe('ABC')
        ->and($order->state)->toBeInstanceOf(Pending::class)
        ->and($cart->fresh()->status)->toBe(CartStatus::Converted);
});

it('keeps the order snapshot immutable when the catalogue changes', function (): void {
    $product = Product::create(['name' => 'Widget', 'sku' => 'ABC', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);
    $order = $this->place->handle($cart);

    $product->update(['price_cents' => 9999, 'name' => 'Renamed']);

    $line = $order->fresh()->lines->first();
    expect($line->unit_price->getMinorAmount()->toInt())->toBe(1000)
        ->and($line->name)->toBe('Widget');
});

it('refuses to place an order from an empty cart', function (): void {
    $cart = $this->carts->create('EUR');

    $this->place->handle($cart);
})->throws(EmptyCartException::class);

it('records domain events in the immutable audit trail', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);
    $this->place->handle($cart);

    expect(DomainEvent::query()->where('name', 'OrderPlaced')->exists())->toBeTrue()
        ->and(DomainEvent::query()->where('name', 'ItemAddedToCart')->exists())->toBeTrue();
});

it('transitions through the state machine and logs every change', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);
    $order = $this->place->handle($cart);

    $transitions = app(TransitionOrderState::class);
    $transitions->handle($order, Confirmed::class, reason: 'payment ok');
    $transitions->handle($order, Processing::class);
    $transitions->handle($order, Completed::class);

    expect($order->state)->toBeInstanceOf(Completed::class)
        ->and($order->transitions()->count())->toBe(4); // placed + 3 transitions
});

it('makes illegal transitions impossible', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);
    $order = $this->place->handle($cart);

    app(TransitionOrderState::class)->handle($order, Completed::class);
})->throws(TransitionNotFound::class);
