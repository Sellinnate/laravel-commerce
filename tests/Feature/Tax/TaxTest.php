<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Enums\CouponType;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Pricing\Models\Coupon;
use Selli\Commerce\Tax\Models\TaxRate;
use Selli\Commerce\Tests\Fixtures\Product;
use Selli\Commerce\Tests\Fixtures\TaxableProduct;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
});

function taxedCart(CartManager $carts, int $priceCents, int $quantity, array $taxContext, array $rate = []): Cart
{
    TaxRate::factory()->create(array_merge(['category' => 'standard', 'country' => 'IT', 'rate' => 2200, 'name' => 'VAT 22%'], $rate));
    $product = Product::create(['name' => 'Widget', 'price_cents' => $priceCents]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, $quantity);
    $carts->setTaxContext($cart, $taxContext);

    return $cart;
}

it('derives inclusive tax from the gross without adding it again', function (): void {
    config()->set('commerce.tax.prices_include_tax', true);
    $cart = taxedCart($this->carts, 12200, 1, ['country' => 'IT']);

    $calc = $this->carts->calculate($cart);

    // 22% of 122.00 inclusive → tax 22.00, grand total stays 122.00.
    expect($calc->taxTotal()->getMinorAmount()->toInt())->toBe(2200)
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(12200);
});

it('adds exclusive tax on top of the net', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    $cart = taxedCart($this->carts, 10000, 1, ['country' => 'IT']);

    $calc = $this->carts->calculate($cart);

    // 22% of 100.00 exclusive → tax 22.00 added → grand total 122.00.
    expect($calc->taxTotal()->getMinorAmount()->toInt())->toBe(2200)
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(12200);
});

it('applies no tax without a jurisdiction', function (): void {
    TaxRate::factory()->create(['country' => 'IT', 'rate' => 2200]);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 10000]);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);

    expect($this->carts->calculate($cart)->taxTotal()->getMinorAmount()->toInt())->toBe(0);
});

it('applies no tax for an exempt customer and annotates the reason', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    $cart = taxedCart($this->carts, 10000, 1, ['country' => 'IT', 'exempt' => true, 'exempt_reason' => 'NGO']);

    $calc = $this->carts->calculate($cart);
    $taxAdjustment = collect($calc->adjustments())->first(fn ($a) => $a->type === AdjustmentType::Tax);

    expect($calc->taxTotal()->getMinorAmount()->toInt())->toBe(0)
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(10000)
        ->and($taxAdjustment?->data['reason'] ?? null)->toBe('NGO');
});

it('applies the B2B intra-EU reverse charge', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    $cart = taxedCart($this->carts, 10000, 1, ['country' => 'IT', 'reverse_charge' => true, 'vat_number' => 'IT123']);

    $calc = $this->carts->calculate($cart);
    $taxAdjustment = collect($calc->adjustments())->first(fn ($a) => $a->type === AdjustmentType::Tax);

    expect($calc->taxTotal()->getMinorAmount()->toInt())->toBe(0)
        ->and($taxAdjustment?->data['reverse_charge'] ?? null)->toBeTrue();
});

it('uses the purchasable tax category to pick a reduced rate', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    TaxRate::factory()->create(['category' => 'reduced', 'country' => 'IT', 'rate' => 1000, 'name' => 'VAT 10%']);

    $product = TaxableProduct::create(['name' => 'Book', 'price_cents' => 10000, 'tax_category' => 'reduced']);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);
    $this->carts->setTaxContext($cart, ['country' => 'IT']);

    // 10% reduced → 10.00 tax.
    expect($this->carts->calculate($cart)->taxTotal()->getMinorAmount()->toInt())->toBe(1000);
});

it('taxes the discounted base after a coupon', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);
    $cart = taxedCart($this->carts, 10000, 1, ['country' => 'IT']);
    $this->carts->applyCoupon($cart, 'SAVE10');

    $calc = $this->carts->calculate($cart);

    // 100.00 − 10% = 90.00 net; 22% tax = 19.80; grand total 90 + 19.80 = 109.80.
    expect($calc->taxTotal()->getMinorAmount()->toInt())->toBe(1980)
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(10980);
});

it('freezes per-line tax onto the order at placement', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    $cart = taxedCart($this->carts, 10000, 1, ['country' => 'IT']);

    $order = app(PlaceOrder::class)->handle($cart);

    expect($order->tax_total->getMinorAmount()->toInt())->toBe(2200)
        ->and($order->grand_total->getMinorAmount()->toInt())->toBe(12200)
        ->and($order->lines->first()->tax_total->getMinorAmount()->toInt())->toBe(2200);
});

it('carries the tax context from a guest cart on merge', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    TaxRate::factory()->create(['category' => 'standard', 'country' => 'IT', 'rate' => 2200, 'name' => 'VAT 22%']);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 10000]);

    $guest = $this->carts->create('EUR');
    $this->carts->add($guest, $product, 1);
    $this->carts->setTaxContext($guest, ['country' => 'IT']);

    $user = $this->carts->create('EUR');

    $this->carts->merge($guest, $user);

    expect($this->carts->taxContext($user)['country'] ?? null)->toBe('IT')
        ->and($this->carts->recalculate($user)->taxTotal()->getMinorAmount()->toInt())->toBe(2200);
});

it('keeps the tax category on a line across idempotent adds', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    TaxRate::factory()->create(['category' => 'reduced', 'country' => 'IT', 'rate' => 1000, 'name' => 'VAT 10%']);

    $product = TaxableProduct::create(['name' => 'Book', 'price_cents' => 5000, 'tax_category' => 'reduced']);
    $cart = $this->carts->create('EUR');
    $this->carts->add($cart, $product, 1);
    $item = $this->carts->add($cart, $product, 1); // idempotent bump to qty 2
    $this->carts->setTaxContext($cart, ['country' => 'IT']);

    // 2 × 50.00 = 100.00 net at the reduced 10% → 10.00 tax.
    expect($item->fresh()->metadata['tax_category'] ?? null)->toBe('reduced')
        ->and($this->carts->calculate($cart)->taxTotal()->getMinorAmount()->toInt())->toBe(1000);
});

it('ignores a caller-supplied tax_category and uses the server default', function (): void {
    config()->set('commerce.tax.prices_include_tax', false);
    TaxRate::factory()->create(['category' => 'standard', 'country' => 'IT', 'rate' => 2200, 'name' => 'VAT 22%']);
    TaxRate::factory()->create(['category' => 'exempt', 'country' => 'IT', 'rate' => 0, 'name' => 'Exempt']);

    // A non-Taxable product; the client tries to smuggle a zero-rate category.
    $product = Product::create(['name' => 'Widget', 'price_cents' => 10000]);
    $cart = $this->carts->create('EUR');
    $item = $this->carts->add($cart, $product, 1, [], ['tax_category' => 'exempt']);
    $this->carts->setTaxContext($cart, ['country' => 'IT']);

    // The injected category is stripped → taxed at the default 'standard' 22%.
    expect($item->metadata['tax_category'] ?? null)->toBeNull()
        ->and($this->carts->calculate($cart)->taxTotal()->getMinorAmount()->toInt())->toBe(2200);
});

it('does not tax when the tax module is disabled', function (): void {
    config()->set('commerce.modules.tax', false);
    $carts = app(CartManager::class);
    TaxRate::factory()->create(['country' => 'IT', 'rate' => 2200]);
    $product = Product::create(['name' => 'Widget', 'price_cents' => 10000]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 1);
    $carts->setTaxContext($cart, ['country' => 'IT']);

    expect($carts->calculate($cart)->taxTotal()->getMinorAmount()->toInt())->toBe(0);
});
