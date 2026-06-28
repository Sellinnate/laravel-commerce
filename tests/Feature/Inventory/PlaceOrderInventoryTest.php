<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Enums\MergeStrategy;
use Selli\Commerce\Enums\StockMovementType;
use Selli\Commerce\Exceptions\InsufficientStockException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Inventory\InventoryManager;
use Selli\Commerce\Inventory\Models\StockItem;
use Selli\Commerce\Inventory\Models\StockMovement;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
    $this->inventory = app(InventoryManager::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
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

it('refuses an expired-hold cart that lost its stock to another buyer', function (): void {
    config()->set('commerce.inventory.reserve_on', 'add_to_cart');
    config()->set('commerce.inventory.reservation_ttl', 30);
    config()->set('commerce.inventory.backorder', 'deny');
    $carts = app(CartManager::class);
    $product = stockedProduct($this->inventory, 3);

    $slow = $carts->create('EUR');
    $carts->add($slow, $product, 3); // holds all 3

    // The hold lapses; the stock is promised to others again.
    Carbon::setTestNow(Carbon::now()->addHour());
    $fast = $carts->create('EUR');
    $carts->add($fast, $product, 3); // ATP is back to 3
    app(PlaceOrder::class)->handle($fast); // ships all 3

    // The dawdling cart's expired hold gives no free pass — it is refused.
    expect(fn () => app(PlaceOrder::class)->handle($slow))
        ->toThrow(InsufficientStockException::class)
        ->and($this->inventory->availableToPromise('product', $product->getPurchasableId(), null))->toBe(0);
    Carbon::setTestNow();
});

it('respects a per-item backorder override at add time', function (): void {
    config()->set('commerce.inventory.backorder', 'deny');
    $product = stockedProduct($this->inventory, 2);

    // This SKU is the exception: it may be back-ordered despite the deny default.
    StockItem::query()
        ->where('purchasable_id', $product->getPurchasableId())
        ->update(['allow_backorder' => true]);

    $cart = app(CartManager::class)->create('EUR');

    // Adding beyond ATP is allowed because the item permits backorder.
    app(CartManager::class)->add($cart, $product, 5);

    expect($cart->fresh()->items)->toHaveCount(1);
});

it('enforces ATP totals when merging carts', function (): void {
    config()->set('commerce.inventory.backorder', 'deny');
    $product = stockedProduct($this->inventory, 3); // place-order timing: no cart holds

    // Each cart fits on its own (3 in stock, no holds), but their sum is 4.
    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 2);
    $user = $this->carts->create('EUR');
    $this->carts->add($user, $product, 2);

    expect(fn () => $this->carts->merge($guest, $user, MergeStrategy::Sum))
        ->toThrow(ProductNotAvailableException::class)
        ->and($user->fresh()->items->first()->quantity)->toBe(2); // rolled back
});

it('does not let a cart hold oversell after stock is adjusted below it', function (): void {
    config()->set('commerce.inventory.reserve_on', 'add_to_cart');
    config()->set('commerce.inventory.backorder', 'deny');
    $carts = app(CartManager::class);
    $product = stockedProduct($this->inventory, 5);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 3); // holds 3 of 5

    // An admin counts down to 1 unit — below the 3 already held.
    $this->inventory->adjust('product', $product->getPurchasableId(), -4);

    // Checkout cannot ship 3 from 1 on-hand under deny: refused, no oversell, and
    // the whole transaction rolls back so on_hand is untouched.
    expect(fn () => app(PlaceOrder::class)->handle($cart))
        ->toThrow(InsufficientStockException::class)
        ->and(StockItem::where('purchasable_id', $product->getPurchasableId())->sum('on_hand'))->toBe(1);
});

it('strips a caller-forged backorder list from the order', function (): void {
    $product = stockedProduct($this->inventory, 10);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);

    // The client tries to smuggle a fake backorder record; fulfilment creates
    // none, so the server-owned key must not survive.
    $order = app(PlaceOrder::class)->handle($cart, [
        'metadata' => ['_backorders' => [['type' => 'product', 'id' => 'forged', 'quantity' => 99]]],
    ]);

    expect($order->fresh()->metadata['_backorders'] ?? null)->toBeNull();
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
