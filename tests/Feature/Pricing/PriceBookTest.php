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
