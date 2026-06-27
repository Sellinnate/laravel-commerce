<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Enums\StockMovementType;
use Selli\Commerce\Exceptions\InsufficientStockException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Inventory\InventoryManager;
use Selli\Commerce\Inventory\Models\StockMovement;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
    $this->inventory = app(InventoryManager::class);
});

function stockedProduct(InventoryManager $inventory, int $onHand, int $priceCents = 1000): Product
{
    $product = Product::create(['name' => 'Widget', 'price_cents' => $priceCents]);
    $inventory->receive('product', $product->getPurchasableId(), $onHand);

    return $product;
}

it('decrements stock when an order is placed', function (): void {
    $product = stockedProduct($this->inventory, 10);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 3);

    app(PlaceOrder::class)->handle($cart);

    expect($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(7)
        ->and(StockMovement::where('purchasable_id', $product->getPurchasableId())->where('type', StockMovementType::Shipment)->sum('quantity'))->toBe(-3);
});

it('blocks adding more than is available to promise', function (): void {
    config()->set('commerce.inventory.backorder', 'deny');
    $product = stockedProduct($this->inventory, 2);
    $cart = $this->carts->create('EUR');

    $this->carts->add($cart, $product, 5);
})->throws(ProductNotAvailableException::class);

it('prevents overselling the last unit at placement', function (): void {
    config()->set('commerce.inventory.backorder', 'deny');
    $product = stockedProduct($this->inventory, 1);

    // Two carts each grab the last unit (the ATP check passes for both before
    // either places — the authoritative guard is at fulfilment).
    $cartA = $this->carts->create('EUR');
    $cartB = $this->carts->create('EUR');
    $this->carts->add($cartA, $product, 1);
    $this->carts->add($cartB, $product, 1);

    app(PlaceOrder::class)->handle($cartA);

    // The second checkout finds no stock and is refused; its order never exists.
    expect(fn () => app(PlaceOrder::class)->handle($cartB))
        ->toThrow(InsufficientStockException::class)
        ->and(Order::count())->toBe(1)
        ->and($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(0);
});

it('records a backorder on the order when the policy allows it', function (): void {
    config()->set('commerce.inventory.backorder', 'allow');
    $product = stockedProduct($this->inventory, 1);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 4);

    $order = app(PlaceOrder::class)->handle($cart);

    expect($order->metadata['_backorders'] ?? null)->toBe([
        ['type' => 'product', 'id' => $product->getPurchasableId(), 'quantity' => 3],
    ])->and($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(-3);
});

it('leaves an untracked product to the host availability', function (): void {
    // No receive(): the product is not stock-tracked, so inventory does not
    // interfere and the order places against the host's own isAvailable.
    $product = Product::create(['name' => 'Service', 'price_cents' => 5000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 99);

    $order = app(PlaceOrder::class)->handle($cart);

    expect($order->lines)->toHaveCount(1)
        ->and($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBeNull();
});

it('does not track stock when the module is disabled', function (): void {
    config()->set('commerce.modules.inventory', false);
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 5);

    app(PlaceOrder::class)->handle($cart);

    // Null inventory writes no movements.
    expect(StockMovement::count())->toBe(0);
});
