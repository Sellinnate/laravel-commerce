<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Contracts\TenantContext;
use Selli\Commerce\Enums\CartStatus;
use Selli\Commerce\Exceptions\CartNotMutableException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Tenancy\CallbackTenantContext;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
    $this->place = app(PlaceOrder::class);
});

it('stamps the order with the cart tenant even without a tenant context', function (): void {
    $tenant = 'tenant-9';
    app()->instance(TenantContext::class, new CallbackTenantContext(function () use (&$tenant) {
        return $tenant;
    }));

    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);

    // Context disappears (e.g. queued/system placement) but the cart keeps its tenant.
    $tenant = null;
    $order = $this->place->handle($cart);

    expect(Order::withoutTenantScope()->find($order->id)?->tenant_id)->toBe('tenant-9');
});

it('allows the same order number across different tenants', function (): void {
    $tenant = 'tenant-a';
    app()->instance(TenantContext::class, new CallbackTenantContext(function () use (&$tenant) {
        return $tenant;
    }));

    $makeOrder = function (): Order {
        $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
        $cart = $this->carts->create('EUR');
        $this->carts->add($cart, $product, 1);

        return $this->place->handle($cart);
    };

    $tenant = 'tenant-a';
    $a = $makeOrder();
    $tenant = 'tenant-b';
    $b = $makeOrder();

    expect($a->number)->toBe('ORD-000001')
        ->and($b->number)->toBe('ORD-000001')
        ->and($a->tenant_id)->toBe('tenant-a')
        ->and($b->tenant_id)->toBe('tenant-b');
});

it('aggregates quantity across option-lines when validating stock at placement', function (): void {
    $product = Product::create(['name' => 'Shirt', 'price_cents' => 2000, 'stock' => 4]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 2, ['size' => 'L']);
    $this->carts->add($cart, $product, 2, ['size' => 'M']);

    // Total 4 fits; now stock drops to 3 and placement must reject the total.
    $product->update(['stock' => 3]);

    $this->place->handle($cart);
})->throws(ProductNotAvailableException::class);

it('re-validates stock at place-order time', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000, 'stock' => 5]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 5);

    // Stock drops after the item was added.
    $product->update(['stock' => 2]);

    $this->place->handle($cart);
})->throws(ProductNotAvailableException::class);

it('cannot convert the same cart twice', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);

    $this->place->handle($cart);

    expect($cart->fresh()->status)->toBe(CartStatus::Converted);

    // A second placement of the already-converted cart is refused.
    $stale = $cart->fresh();
    $stale->setRelation('items', $cart->items);
    $this->place->handle($stale);
})->throws(CartNotMutableException::class);

it('combines duplicate source lines into one target line on merge', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);

    $guest = $this->carts->create('EUR');
    // Two source lines for the same purchasable (idempotency disabled to force it).
    config()->set('commerce.cart.idempotent_add', false);
    $this->carts->add($guest, $product, 1);
    $this->carts->add($guest, $product, 2);
    config()->set('commerce.cart.idempotent_add', true);

    $user = $this->carts->create('EUR');

    $this->carts->merge($guest, $user);

    expect($user->items)->toHaveCount(1)
        ->and($user->items->first()->quantity)->toBe(3);
});
