<?php

declare(strict_types=1);

use Brick\Money\Money;
use Illuminate\Support\Facades\Event;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Enums\CouponType;
use Selli\Commerce\Events\Order\OrderPlaced;
use Selli\Commerce\Events\Pricing\CouponApplied;
use Selli\Commerce\Events\Pricing\CouponRejected;
use Selli\Commerce\Exceptions\CouponCurrencyMismatchException;
use Selli\Commerce\Exceptions\CouponExpiredException;
use Selli\Commerce\Exceptions\CouponInactiveException;
use Selli\Commerce\Exceptions\CouponMinimumNotMetException;
use Selli\Commerce\Exceptions\CouponNotFoundException;
use Selli\Commerce\Exceptions\CouponUsageLimitReachedException;
use Selli\Commerce\Exceptions\PricingModuleDisabledException;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Pricing\Listeners\RecordPricingUsage;
use Selli\Commerce\Pricing\Models\Coupon;
use Selli\Commerce\Pricing\Models\CouponRedemption;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
});

function cartWith(CartManager $carts, int $priceCents, int $quantity = 1): array
{
    $product = Product::create(['name' => 'Widget', 'price_cents' => $priceCents]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, $quantity);

    return [$cart, $product];
}

it('applies a percentage coupon as a discount', function (): void {
    Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);
    [$cart] = cartWith($this->carts, 1000, 2);

    $this->carts->applyCoupon($cart, 'SAVE10');
    $calc = $this->carts->calculate($cart);

    expect($calc->discountTotal()->getMinorAmount()->toInt())->toBe(-200)
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(1800)
        ->and($this->carts->coupons($cart))->toBe(['SAVE10']);
});

it('applies a fixed coupon capped at the subtotal', function (): void {
    Coupon::factory()->fixed(500)->create(['code' => 'TENOFF']);
    [$cart] = cartWith($this->carts, 300, 1);

    $this->carts->applyCoupon($cart, 'TENOFF');

    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(0);
});

it('rejects an unknown coupon', function (): void {
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'NOPE');
})->throws(CouponNotFoundException::class);

it('does not honour another tenant coupon on a null-tenant cart', function (): void {
    // A coupon that belongs to another tenant must not apply to a null-tenant cart.
    Coupon::factory()->create(['tenant_id' => 'other-tenant', 'code' => 'OTHER', 'type' => CouponType::Percentage, 'value' => 50]);
    [$cart] = cartWith($this->carts, 1000);

    $this->carts->applyCoupon($cart, 'OTHER');
})->throws(CouponNotFoundException::class);

it('rejects an expired coupon', function (): void {
    Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'OLD');
})->throws(CouponExpiredException::class);

it('rejects an inactive coupon', function (): void {
    Coupon::factory()->create(['code' => 'OFF', 'active' => false]);
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'OFF');
})->throws(CouponInactiveException::class);

it('rejects a coupon that reached its global usage limit', function (): void {
    Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1, 'usage_count' => 1]);
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'ONCE');
})->throws(CouponUsageLimitReachedException::class);

it('rejects a coupon below its minimum spend', function (): void {
    Coupon::factory()->create(['code' => 'BIG', 'min_amount' => 5000, 'min_amount_currency' => 'EUR']);
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'BIG');
})->throws(CouponMinimumNotMetException::class);

it('rejects a fixed coupon in a different currency', function (): void {
    Coupon::factory()->fixed(500, 'USD')->create(['code' => 'USD5']);
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'USD5');
})->throws(CouponCurrencyMismatchException::class);

it('rejects a coupon whose minimum is in a different currency', function (): void {
    Coupon::factory()->create(['code' => 'MINUSD', 'min_amount' => 100, 'min_amount_currency' => 'USD']);
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'MINUSD');
})->throws(CouponCurrencyMismatchException::class);

it('emits CouponApplied and CouponRejected events', function (): void {
    Event::fake([CouponApplied::class, CouponRejected::class]);
    $carts = app(CartManager::class);

    Coupon::factory()->create(['code' => 'OK', 'type' => CouponType::Percentage, 'value' => 5]);
    [$cart] = cartWith($carts, 1000);

    $carts->applyCoupon($cart, 'OK');
    Event::assertDispatched(CouponApplied::class);

    try {
        $carts->applyCoupon($cart, 'MISSING');
    } catch (CouponNotFoundException) {
        // expected
    }
    Event::assertDispatched(CouponRejected::class);
});

it('removes an applied coupon', function (): void {
    Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);
    [$cart] = cartWith($this->carts, 1000);

    $this->carts->applyCoupon($cart, 'SAVE10');
    $this->carts->removeCoupon($cart, 'SAVE10');

    expect($this->carts->coupons($cart))->toBe([])
        ->and($this->carts->calculate($cart)->discountTotal()->getMinorAmount()->toInt())->toBe(0);
});

it('rejects a coupon that reached its per-customer limit', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'PERCUST', 'per_customer_limit' => 1]);
    $coupon->redemptions()->create([
        'tenant_id' => null,
        'customer_type' => 'customer',
        'customer_id' => 'cust-1',
        'amount' => 100,
        'currency' => 'EUR',
    ]);

    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $cart = $this->carts->forOwner('customer', 'cust-1', 'EUR');
    $this->carts->add($cart, $product, 1);

    $this->carts->applyCoupon($cart, 'PERCUST');
})->throws(CouponUsageLimitReachedException::class);

it('rejects a per-customer-limited coupon on a guest (unidentified) cart', function (): void {
    Coupon::factory()->create(['code' => 'PERCUST', 'per_customer_limit' => 1]);
    [$cart] = cartWith($this->carts, 1000); // guest cart, no owner

    $this->carts->applyCoupon($cart, 'PERCUST');
})->throws(CouponUsageLimitReachedException::class);

it('skips a coupon that became invalid before calculation', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);
    [$cart] = cartWith($this->carts, 1000);
    $this->carts->applyCoupon($cart, 'SAVE10');

    // Coupon is deactivated after being applied.
    $coupon->update(['active' => false]);

    expect($this->carts->calculate($cart)->discountTotal()->getMinorAmount()->toInt())->toBe(0);
});

it('refuses coupons when the pricing module is disabled', function (): void {
    config()->set('commerce.modules.pricing', false);
    $carts = app(CartManager::class);
    [$cart] = cartWith($carts, 1000);

    $carts->applyCoupon($cart, 'ANY');
})->throws(PricingModuleDisabledException::class);

it('carries applied coupon codes when a guest cart merges into a user cart', function (): void {
    Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 1);
    $this->carts->applyCoupon($guest, 'SAVE10');

    $user = $this->carts->create('EUR');
    $this->carts->add($user, $product, 1);

    $this->carts->merge($guest, $user);

    // Merged quantity is 2 (1 + 1) → subtotal 2000 → 10% = 200 discount.
    expect($this->carts->coupons($user))->toContain('SAVE10')
        ->and($this->carts->calculate($user)->discountTotal()->getMinorAmount()->toInt())->toBe(-200);
});

it('never mutates the frozen order total at settlement and records usage truthfully', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1, 'usage_count' => 1]);

    $order = Order::factory()->create([
        'currency' => 'EUR',
        'grand_total' => Money::ofMinor(1800, 'EUR'),
        'metadata' => ['_adjustments' => [[
            'type' => 'discount',
            'label' => 'Coupon ONCE',
            'amount' => -200,
            'currency' => 'EUR',
            'source' => 'coupon',
            'affects_total' => true,
            'data' => ['code' => 'ONCE', 'coupon_id' => $coupon->id],
        ]]],
    ]);

    app(RecordPricingUsage::class)->handle(new OrderPlaced($order));

    // The placed order is authoritative and is never mutated; consumption is
    // recorded truthfully (usage_count is the source of truth).
    expect($order->fresh()->grand_total->getMinorAmount()->toInt())->toBe(1800)
        ->and(CouponRedemption::query()->where('coupon_id', $coupon->id)->where('order_id', $order->id)->count())->toBe(1);
});

it('records coupon usage when the order is placed', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);
    [$cart] = cartWith($this->carts, 1000, 2);
    $this->carts->applyCoupon($cart, 'SAVE10');

    app(PlaceOrder::class)->handle($cart);

    expect($coupon->fresh()->usage_count)->toBe(1)
        ->and(CouponRedemption::query()->where('coupon_id', $coupon->id)->where('amount', 200)->exists())->toBeTrue();
});

it('does not double-count coupon usage when the placed event is replayed', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);
    [$cart] = cartWith($this->carts, 1000, 2);
    $this->carts->applyCoupon($cart, 'SAVE10');
    $order = app(PlaceOrder::class)->handle($cart);

    app(RecordPricingUsage::class)
        ->handle(new OrderPlaced($order));

    expect($coupon->fresh()->usage_count)->toBe(1)
        ->and(CouponRedemption::query()->where('coupon_id', $coupon->id)->count())->toBe(1);
});
