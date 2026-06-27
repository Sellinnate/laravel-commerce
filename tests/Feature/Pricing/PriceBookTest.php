<?php

declare(strict_types=1);

use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Contracts\PriceResolver;
use Selli\Commerce\Pricing\Models\PriceBook;
use Selli\Commerce\Tests\Fixtures\Product;

function priceBookWith(string $productId, int $amount, array $book = [], int $minQuantity = 1): PriceBook
{
    /** @var PriceBook $priceBook */
    $priceBook = PriceBook::factory()->create(array_merge(['currency' => 'EUR'], $book));
    $priceBook->prices()->create([
        'purchasable_type' => 'product',
        'purchasable_id' => $productId,
        'amount' => $amount,
        'currency' => 'EUR',
        'min_quantity' => $minQuantity,
    ]);

    return $priceBook;
}

it('resolves a price from a price book over the purchasable default', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    priceBookWith($product->id, 800);

    expect(app(PriceResolver::class)->resolve($product, 'EUR')->getMinorAmount()->toInt())->toBe(800);
});

it('does not use another tenant price book even without an ambient tenant context', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    // A price book belonging to a different tenant should never win.
    priceBookWith($product->id, 500, ['tenant_id' => 'other-tenant']);

    // No ambient tenant context, and the resolution context carries no tenant.
    expect(app(PriceResolver::class)->resolve($product, 'EUR')->getMinorAmount()->toInt())->toBe(1000)
        ->and(app(PriceResolver::class)->resolve($product, 'EUR', ['tenant_id' => 'other-tenant'])->getMinorAmount()->toInt())->toBe(500);
});

it('falls back to the purchasable price when no book applies', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);

    expect(app(PriceResolver::class)->resolve($product, 'EUR')->getMinorAmount()->toInt())->toBe(1000);
});

it('prefers a segment-specific book over the default', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    priceBookWith($product->id, 800);
    priceBookWith($product->id, 700, ['segment' => 'vip']);

    expect(app(PriceResolver::class)->resolve($product, 'EUR', ['segment' => 'vip'])->getMinorAmount()->toInt())->toBe(700)
        ->and(app(PriceResolver::class)->resolve($product, 'EUR')->getMinorAmount()->toInt())->toBe(800);
});

it('applies quantity tiers', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $book = priceBookWith($product->id, 1000, [], 1);
    $book->prices()->create([
        'purchasable_type' => 'product',
        'purchasable_id' => $product->id,
        'amount' => 800,
        'currency' => 'EUR',
        'min_quantity' => 10,
    ]);

    expect(app(PriceResolver::class)->resolve($product, 'EUR', ['quantity' => 10])->getMinorAmount()->toInt())->toBe(800)
        ->and(app(PriceResolver::class)->resolve($product, 'EUR', ['quantity' => 1])->getMinorAmount()->toInt())->toBe(1000);
});

it('ignores expired price books', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    priceBookWith($product->id, 800, ['ends_at' => now()->subDay()]);

    expect(app(PriceResolver::class)->resolve($product, 'EUR')->getMinorAmount()->toInt())->toBe(1000);
});

it('uses the price book price when adding to a cart', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    priceBookWith($product->id, 750);

    $carts = app(CartManager::class);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 2);

    expect($carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(1500);
});

it('applies a quantity tier when adding to a cart', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $book = priceBookWith($product->id, 1000, [], 1);
    $book->prices()->create([
        'purchasable_type' => 'product',
        'purchasable_id' => $product->id,
        'amount' => 700,
        'currency' => 'EUR',
        'min_quantity' => 10,
    ]);

    $carts = app(CartManager::class);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 10);

    expect($carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(7000);
});

it('re-prices to a quantity tier when idempotent adds cross the threshold', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $book = priceBookWith($product->id, 1000, [], 1);
    $book->prices()->create([
        'purchasable_type' => 'product',
        'purchasable_id' => $product->id,
        'amount' => 700,
        'currency' => 'EUR',
        'min_quantity' => 10,
    ]);

    $carts = app(CartManager::class);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, 5);  // below tier → 1000 each
    $carts->add($cart, $product, 5);  // combined 10 → tier 700 each

    expect($cart->items->first()->quantity)->toBe(10)
        ->and($carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(7000);
});

it('re-prices to a quantity tier when setQuantity crosses the threshold', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $book = priceBookWith($product->id, 1000, [], 1);
    $book->prices()->create([
        'purchasable_type' => 'product',
        'purchasable_id' => $product->id,
        'amount' => 700,
        'currency' => 'EUR',
        'min_quantity' => 10,
    ]);

    $carts = app(CartManager::class);
    $cart = $carts->create('EUR');
    $item = $carts->add($cart, $product, 1);
    $carts->setQuantity($cart, $item, 10);

    expect($carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(7000);
});

it('re-prices to a quantity tier when a merge sums across the threshold', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    $book = priceBookWith($product->id, 1000, [], 1);
    $book->prices()->create([
        'purchasable_type' => 'product',
        'purchasable_id' => $product->id,
        'amount' => 700,
        'currency' => 'EUR',
        'min_quantity' => 10,
    ]);

    $carts = app(CartManager::class);
    $guest = $carts->create('EUR');
    $carts->add($guest, $product, 6);
    $user = $carts->create('EUR');
    $carts->add($user, $product, 4);

    $carts->merge($guest, $user);

    // Combined 10 → tier price 700 each.
    expect($carts->calculate($user)->grandTotal()->getMinorAmount()->toInt())->toBe(7000);
});

it('carries the pricing segment from a guest cart on merge', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    priceBookWith($product->id, 800);
    priceBookWith($product->id, 700, ['segment' => 'vip']);

    $carts = app(CartManager::class);
    $guest = $carts->create('EUR');
    $guest->metadata = ['segment' => 'vip'];
    $guest->save();
    $carts->add($guest, $product, 1);

    $user = $carts->create('EUR');

    $carts->merge($guest, $user);

    expect(($user->metadata ?? [])['segment'] ?? null)->toBe('vip')
        ->and($carts->recalculate($user)->grandTotal()->getMinorAmount()->toInt())->toBe(700);
});

it('uses a segment-specific price when the cart carries a segment', function (): void {
    $product = Product::create(['name' => 'Widget', 'price_cents' => 1000]);
    priceBookWith($product->id, 800);
    priceBookWith($product->id, 700, ['segment' => 'vip']);

    $carts = app(CartManager::class);
    $cart = $carts->create('EUR');
    $cart->metadata = ['segment' => 'vip'];
    $cart->save();

    $carts->add($cart, $product, 1);

    expect($carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(700);
});
