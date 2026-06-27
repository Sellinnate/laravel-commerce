<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Contracts\OrderNumberGenerator;
use Selli\Commerce\Exceptions\CartNotMutableException;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Tests\Fixtures\Product;

it('produces a gap-free sequence from the locked counter', function (): void {
    $generator = app(OrderNumberGenerator::class);

    expect($generator->generate(null))->toBe('ORD-000001')
        ->and($generator->generate(null))->toBe('ORD-000002')
        ->and($generator->generate(null))->toBe('ORD-000003');
});

it('keeps independent sequences per tenant', function (): void {
    $generator = app(OrderNumberGenerator::class);

    expect($generator->generate('tenant-a'))->toBe('ORD-000001')
        ->and($generator->generate('tenant-b'))->toBe('ORD-000001')
        ->and($generator->generate('tenant-a'))->toBe('ORD-000002');
});

it('refuses to recalculate a converted cart', function (): void {
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);
    app(PlaceOrder::class)->handle($cart);

    $carts->recalculate($cart);
})->throws(CartNotMutableException::class);
