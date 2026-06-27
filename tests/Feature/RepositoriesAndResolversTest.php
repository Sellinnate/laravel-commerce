<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Selli\Commerce\Contracts\OrderRepository;
use Selli\Commerce\Contracts\PurchasableResolver;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\States\Confirmed;
use Selli\Commerce\Tests\Fixtures\Customer;
use Selli\Commerce\Tests\Fixtures\Product;

it('finds orders by id and number and saves them', function (): void {
    $order = Order::factory()->create(['number' => 'ORD-XYZ']);
    $repo = app(OrderRepository::class);

    expect($repo->find($order->id)?->id)->toBe($order->id)
        ->and($repo->findByNumber('ORD-XYZ')?->id)->toBe($order->id)
        ->and($repo->find('does-not-exist'))->toBeNull();

    $order->metadata = ['note' => 'vip'];
    $saved = $repo->save($order);
    expect($saved->fresh()->metadata['note'])->toBe('vip');
});

it('resolves a live purchasable and returns null for unknown references', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $resolver = app(PurchasableResolver::class);

    expect($resolver->resolve('product', $product->id)?->getPurchasableId())->toBe($product->id)
        ->and($resolver->resolve('product', 'missing-id'))->toBeNull()
        ->and($resolver->resolve('unmapped-type', 'x'))->toBeNull();
});

it('permits order actions by default policy', function (): void {
    $user = Customer::create(['name' => 'Agent']);
    $order = Order::factory()->create();

    expect(Gate::forUser($user)->allows('view', $order))->toBeTrue()
        ->and(Gate::forUser($user)->allows('transition', [$order, Confirmed::class]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('refund', $order))->toBeTrue()
        ->and(Gate::forUser($user)->allows('applyManualDiscount', $order))->toBeTrue();
});
