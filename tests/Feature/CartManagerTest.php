<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Enums\MergeStrategy;
use Selli\Commerce\Events\Cart\ItemAddedToCart;
use Selli\Commerce\Exceptions\InvalidQuantityException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
});

it('adds an item and computes the total', function (): void {
    $product = Product::create(['name' => 'Widget', 'sku' => 'W1', 'price_cents' => 1500]);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 2);

    expect($cart->items)->toHaveCount(1)
        ->and($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(3000);
});

it('increments quantity idempotently when adding the same purchasable', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 1);
    $this->carts->add($cart, $product, 2);

    expect($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->quantity)->toBe(3);
});

it('keeps separate lines for different options', function (): void {
    $product = Product::create(['name' => 'Shirt', 'price_cents' => 2000]);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 1, ['size' => 'L']);
    $this->carts->add($cart, $product, 1, ['size' => 'M']);

    expect($cart->items)->toHaveCount(2);
});

it('rejects a non-positive quantity', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 0);
})->throws(InvalidQuantityException::class);

it('rejects an unavailable product', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000, 'available' => false]);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 1);
})->throws(ProductNotAvailableException::class);

it('rejects exceeding available stock', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000, 'stock' => 3]);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 5);
})->throws(ProductNotAvailableException::class);

it('removes and clears items', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $item = $this->carts->add($cart, $product, 1);

    $this->carts->remove($cart, $item);
    expect($cart->fresh()->items)->toHaveCount(0);

    $this->carts->add($cart, $product, 1);
    $this->carts->clear($cart);
    expect($cart->fresh()->items)->toHaveCount(0);
});

it('merges a guest cart into a user cart summing quantities', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 2);

    $user = $this->carts->create('EUR');
    $this->carts->add($user, $product, 1);

    $this->carts->merge($guest, $user, MergeStrategy::Sum);

    expect($user->items)->toHaveCount(1)
        ->and($user->items->first()->quantity)->toBe(3)
        ->and($guest->fresh()->status->value)->toBe('merged');
});

it('emits an ItemAddedToCart event', function (): void {
    Event::fake([ItemAddedToCart::class]);

    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);

    Event::assertDispatched(ItemAddedToCart::class);
});
