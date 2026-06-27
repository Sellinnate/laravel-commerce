<?php

declare(strict_types=1);

use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Contracts\TenantContext;
use Selli\Commerce\Tenancy\CallbackTenantContext;
use Selli\Commerce\Tenancy\NullTenantContext;

it('treats a null tenant context as single-tenant', function (): void {
    $context = new NullTenantContext;

    expect($context->currentTenantId())->toBeNull()
        ->and($context->hasTenant())->toBeFalse();
});

it('resolves the tenant id from a callback', function (): void {
    $context = new CallbackTenantContext(fn (): string => 'tenant-7');

    expect($context->currentTenantId())->toBe('tenant-7')
        ->and($context->hasTenant())->toBeTrue();
});

it('coerces non-scalar callback results to null', function (): void {
    $context = new CallbackTenantContext(fn () => null);

    expect($context->currentTenantId())->toBeNull()
        ->and($context->hasTenant())->toBeFalse();
});

it('scopes domain rows to the current tenant', function (): void {
    $tenant = 'tenant-a';
    app()->instance(TenantContext::class, new CallbackTenantContext(function () use (&$tenant) {
        return $tenant;
    }));

    Cart::create(['currency' => 'EUR']);
    expect(Cart::query()->count())->toBe(1);

    $tenant = 'tenant-b';
    expect(Cart::query()->count())->toBe(0);

    $tenant = 'tenant-a';
    expect(Cart::query()->count())->toBe(1);
});

it('stamps the tenant id automatically on create', function (): void {
    app()->instance(TenantContext::class, new CallbackTenantContext(fn (): string => 'tenant-x'));

    $cart = Cart::create(['currency' => 'EUR']);

    expect($cart->tenant_id)->toBe('tenant-x');
});
