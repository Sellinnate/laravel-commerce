<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Contracts\CartRepository;
use Selli\Commerce\Enums\MergeStrategy;
use Selli\Commerce\Exceptions\CartItemMismatchException;
use Selli\Commerce\Exceptions\CartNotFoundException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
});

it('refuses to remove an item that belongs to another cart', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cartA = $this->carts->create('EUR');
    $cartB = $this->carts->create('EUR');
    $item = $this->carts->add($cartA, $product, 1);

    $this->carts->remove($cartB, $item);
})->throws(CartItemMismatchException::class);

it('refuses to set quantity on an item from another cart', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cartA = $this->carts->create('EUR');
    $cartB = $this->carts->create('EUR');
    $item = $this->carts->add($cartA, $product, 1);

    $this->carts->setQuantity($cartB, $item, 3);
})->throws(CartItemMismatchException::class);

it('treats merging a cart into itself as a no-op', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 2);

    $result = $this->carts->merge($cart, $cart);

    expect($result->is($cart))->toBeTrue()
        ->and($cart->fresh()->items)->toHaveCount(1)
        ->and($cart->fresh()->status->value)->toBe('active');
});

it('rejects a merge that would exceed available stock and rolls back', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000, 'stock' => 3]);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 2);
    $user = $this->carts->create('EUR');
    $this->carts->add($user, $product, 2);

    expect(fn () => $this->carts->merge($guest, $user, MergeStrategy::Sum))
        ->toThrow(ProductNotAvailableException::class);

    // Rolled back: source not marked merged, target quantity unchanged.
    expect($guest->fresh()->status->value)->toBe('active')
        ->and($user->fresh()->items->first()->quantity)->toBe(2);
});

it('rejects total quantity across option-lines exceeding stock', function (): void {
    $product = Product::create(['name' => 'Shirt', 'price_cents' => 2000, 'stock' => 3]);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 2, ['size' => 'L']);

    expect(fn () => $this->carts->add($cart, $product, 2, ['size' => 'M']))
        ->toThrow(ProductNotAvailableException::class);
});

it('refuses to add to a cart whose row no longer exists', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');

    Cart::withoutTenantScope()->whereKey($cart->id)->delete();

    $this->carts->add($cart, $product, 1);
})->throws(CartNotFoundException::class);

it('throws for an unsupported cart driver', function (): void {
    config()->set('commerce.cart.driver', 'session');

    app()->forgetInstance(CartRepository::class);
    app()->make(CartRepository::class);
})->throws(InvalidArgumentException::class);
