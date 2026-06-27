<?php

declare(strict_types=1);

use Brick\Money\Money;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Contracts\OrderRepository;
use Selli\Commerce\Contracts\TenantContext;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Tenancy\CallbackTenantContext;
use Selli\Commerce\Tests\Fixtures\Product;

it('does not leak orders across tenants when looked up by number', function (): void {
    Order::factory()->create(['tenant_id' => 'tenant-a', 'number' => 'ORD-000001']);
    Order::factory()->create(['tenant_id' => 'tenant-b', 'number' => 'ORD-000001']);

    app()->instance(TenantContext::class, new CallbackTenantContext(fn (): string => 'tenant-a'));
    expect(app(OrderRepository::class)->findByNumber('ORD-000001')?->tenant_id)->toBe('tenant-a');

    app()->instance(TenantContext::class, new CallbackTenantContext(fn (): string => 'tenant-b'));
    expect(app(OrderRepository::class)->findByNumber('ORD-000001')?->tenant_id)->toBe('tenant-b');
});

it('only matches null-tenant orders by number when no tenant is active', function (): void {
    Order::factory()->create(['tenant_id' => 'tenant-a', 'number' => 'ORD-000001']);

    expect(app(OrderRepository::class)->findByNumber('ORD-000001'))->toBeNull();
});

it('never lets the caller override authoritative order fields', function (): void {
    $carts = app(CartManager::class);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 2);

    $order = app(PlaceOrder::class)->handle($cart, [
        'tenant_id' => 'evil-tenant',
        'currency' => 'USD',
        'grand_total' => Money::ofMinor(1, 'USD'),
        'billing_address' => ['city' => 'Rome'],
    ]);

    expect($order->tenant_id)->toBeNull()
        ->and($order->currency)->toBe('EUR')
        ->and($order->grand_total->getMinorAmount()->toInt())->toBe(2000)
        ->and($order->grand_total->getCurrency()->getCurrencyCode())->toBe('EUR')
        ->and($order->billing_address['city'])->toBe('Rome');
});
