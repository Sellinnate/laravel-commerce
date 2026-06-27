<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Inventory\InventoryManager;
use Selli\Commerce\Inventory\Models\StockReservation;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    // Hold stock the moment a line is added, not only at checkout.
    config()->set('commerce.inventory.reserve_on', 'add_to_cart');
    $this->carts = app(CartManager::class);
    $this->inventory = app(InventoryManager::class);
});

function heldProduct(InventoryManager $inventory, int $onHand): Product
{
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $inventory->receive('product', $product->getPurchasableId(), $onHand);

    return $product;
}

it('holds stock when a line is added to the cart', function (): void {
    $product = heldProduct($this->inventory, 10);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 3);

    expect($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(7)
        ->and(StockReservation::where('reference_id', $cart->id)->where('quantity', 3)->exists())->toBeTrue();
});

it('updates the hold when the quantity changes', function (): void {
    $product = heldProduct($this->inventory, 10);
    $cart = $this->carts->create('EUR');
    $item = $this->carts->add($cart, $product, 2);
    $this->carts->setQuantity($cart, $item, 5);

    expect($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(5);
});

it('releases the hold when the line is removed', function (): void {
    $product = heldProduct($this->inventory, 10);
    $cart = $this->carts->create('EUR');
    $item = $this->carts->add($cart, $product, 3);

    $this->carts->remove($cart, $item);

    expect($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(10);
});

it('releases holds when the cart is cleared', function (): void {
    $product = heldProduct($this->inventory, 10);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 3);

    $this->carts->clear($cart);

    expect($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(10);
});

it('a second cart cannot hold stock already held by the first', function (): void {
    config()->set('commerce.inventory.backorder', 'deny');
    $product = heldProduct($this->inventory, 3);

    $cartA = $this->carts->create('EUR');
    $this->carts->add($cartA, $product, 3); // holds all 3

    $cartB = $this->carts->create('EUR');

    expect(fn () => $this->carts->add($cartB, $product, 1))
        ->toThrow(ProductNotAvailableException::class);
});

it('consumes the cart hold at placement without double counting', function (): void {
    $product = heldProduct($this->inventory, 10);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 3);

    app(PlaceOrder::class)->handle($cart);

    // The 3 held units shipped: ATP 7, and no active cart reservation lingers.
    expect($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(7)
        ->and(StockReservation::where('reference_id', $cart->id)->where('status', 'active')->count())->toBe(0);
});

it('carries holds from a guest cart onto the user cart at merge', function (): void {
    $product = heldProduct($this->inventory, 10);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 2);

    $user = $this->carts->create('EUR');
    $this->carts->merge($guest, $user);

    // Still only 2 held in total (on the surviving user cart), ATP 8.
    expect($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(8)
        ->and(StockReservation::where('reference_id', $user->id)->where('status', 'active')->where('quantity', 2)->exists())->toBeTrue()
        ->and(StockReservation::where('reference_id', $guest->id)->where('status', 'active')->count())->toBe(0);
});
