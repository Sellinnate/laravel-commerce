<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Enums\MergeStrategy;
use Selli\Commerce\Events\Cart\CartCleared;
use Selli\Commerce\Exceptions\CurrencyMismatchException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
});

it('sets an explicit quantity', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $item = $this->carts->add($cart, $product, 1);

    $this->carts->setQuantity($cart, $item, 4);

    expect($item->fresh()->quantity)->toBe(4);
});

it('rejects setting a quantity above stock', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000, 'stock' => 2]);
    $cart = $this->carts->create('EUR');
    $item = $this->carts->add($cart, $product, 1);

    $this->carts->setQuantity($cart, $item, 5);
})->throws(ProductNotAvailableException::class);

it('recalculates live unit prices from the catalogue', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 2);

    $product->update(['price_cents' => 1500]);
    $calculation = $this->carts->recalculate($cart);

    expect($calculation->grandTotal()->getMinorAmount()->toInt())->toBe(3000)
        ->and($cart->items->first()->unit_price->getMinorAmount()->toInt())->toBe(1500);
});

it('finds or creates the active cart for an owner', function (): void {
    $first = $this->carts->forOwner('customer', 'cust-1', 'EUR');
    $second = $this->carts->forOwner('customer', 'cust-1', 'EUR');

    expect($second->id)->toBe($first->id);
});

it('merges keeping the highest quantity', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 5);

    $user = $this->carts->create('EUR');
    $this->carts->add($user, $product, 2);

    $this->carts->merge($guest, $user, MergeStrategy::KeepHighestQuantity);

    expect($user->items->first()->quantity)->toBe(5);
});

it('merges replacing the quantity and moving unmatched lines', function (): void {
    $a = Product::create(['name' => 'A', 'price_cents' => 1000]);
    $b = Product::create(['name' => 'B', 'price_cents' => 2000]);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $a, 9);
    $this->carts->add($guest, $b, 1);

    $user = $this->carts->create('EUR');
    $this->carts->add($user, $a, 2);

    $this->carts->merge($guest, $user, MergeStrategy::Replace);

    expect($user->items)->toHaveCount(2)
        ->and($user->items->firstWhere('purchasable_id', $a->id)->quantity)->toBe(9);
});

it('refuses to merge carts of different currencies', function (): void {
    $guest = $this->carts->create('EUR');
    $user = $this->carts->create('USD');

    $this->carts->merge($guest, $user);
})->throws(CurrencyMismatchException::class);

it('uses the configured default merge strategy when none is given', function (): void {
    config()->set('commerce.cart.merge_strategy', 'sum');
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 2);
    $user = $this->carts->create('EUR');
    $this->carts->add($user, $product, 3);

    $this->carts->merge($guest, $user);

    expect($user->items->first()->quantity)->toBe(5);
});

it('emits a CartCleared event', function (): void {
    Event::fake([CartCleared::class]);
    $carts = app(CartManager::class);

    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);
    $carts->clear($cart);

    Event::assertDispatched(CartCleared::class);
});
