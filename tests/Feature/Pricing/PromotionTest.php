<?php

declare(strict_types=1);

use Selli\Commerce\Audit\Models\DomainEvent;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Enums\StackingPolicy;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Pricing\Models\Promotion;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
});

function cartSubtotal(CartManager $carts, int $priceCents, int $quantity): array
{
    $product = Product::create(['name' => 'Widget', 'price_cents' => $priceCents]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, $quantity);

    return [$cart, $product];
}

it('applies a matching percentage promotion', function (): void {
    Promotion::factory()->create([
        'name' => '10% over €10',
        'conditions' => [['type' => 'cart_subtotal_min', 'amount' => 1000, 'currency' => 'EUR']],
        'actions' => [['type' => 'percentage_off', 'percent' => 10]],
    ]);

    [$cart] = cartSubtotal($this->carts, 1000, 2); // 2000

    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(1800);
});

it('does not apply a promotion whose conditions fail', function (): void {
    Promotion::factory()->create([
        'conditions' => [['type' => 'cart_subtotal_min', 'amount' => 5000, 'currency' => 'EUR']],
        'actions' => [['type' => 'percentage_off', 'percent' => 10]],
    ]);

    [$cart] = cartSubtotal($this->carts, 1000, 1);

    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(1000);
});

it('stacks cumulative promotions', function (): void {
    Promotion::factory()->create(['name' => 'A', 'stacking' => StackingPolicy::Cumulative, 'actions' => [['type' => 'percentage_off', 'percent' => 10]]]);
    Promotion::factory()->create(['name' => 'B', 'stacking' => StackingPolicy::Cumulative, 'actions' => [['type' => 'fixed_off', 'amount' => 100, 'currency' => 'EUR']]]);

    [$cart] = cartSubtotal($this->carts, 1000, 1); // 1000

    // 10% (=100) + fixed 100 = 200 off
    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(800);
});

it('applies only the highest-priority exclusive promotion', function (): void {
    Promotion::factory()->create(['name' => 'Exclusive', 'priority' => 10, 'stacking' => StackingPolicy::Exclusive, 'actions' => [['type' => 'percentage_off', 'percent' => 20]]]);
    Promotion::factory()->create(['name' => 'Other', 'priority' => 1, 'stacking' => StackingPolicy::Cumulative, 'actions' => [['type' => 'percentage_off', 'percent' => 10]]]);

    [$cart] = cartSubtotal($this->carts, 1000, 1);

    // Only the exclusive 20% applies.
    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(800);
});

it('applies only the best promotion under best-of', function (): void {
    Promotion::factory()->create(['name' => 'Small', 'priority' => 5, 'stacking' => StackingPolicy::BestOf, 'actions' => [['type' => 'percentage_off', 'percent' => 5]]]);
    Promotion::factory()->create(['name' => 'Big', 'priority' => 1, 'stacking' => StackingPolicy::BestOf, 'actions' => [['type' => 'percentage_off', 'percent' => 30]]]);

    [$cart] = cartSubtotal($this->carts, 1000, 1);

    // Best-of picks the 30% even though the 5% has higher priority.
    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(700);
});

it('prefers a larger exclusive offer over a smaller higher-priority cumulative one', function (): void {
    Promotion::factory()->create(['name' => 'Small cumulative', 'priority' => 10, 'stacking' => StackingPolicy::Cumulative, 'actions' => [['type' => 'percentage_off', 'percent' => 5]]]);
    Promotion::factory()->create(['name' => 'Big exclusive', 'priority' => 1, 'stacking' => StackingPolicy::Exclusive, 'actions' => [['type' => 'percentage_off', 'percent' => 50]]]);

    [$cart] = cartSubtotal($this->carts, 1000, 1);

    // Cumulative 5% (=50) vs exclusive 50% (=500): the exclusive wins, not dropped.
    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(500);
});

it('records a free-shipping promotion as a tracked adjustment', function (): void {
    Promotion::factory()->create([
        'name' => 'Free shipping',
        'actions' => [['type' => 'free_shipping']],
    ]);

    [$cart] = cartSubtotal($this->carts, 1000, 1);
    $calc = $this->carts->calculate($cart);

    $shipping = array_filter($calc->adjustments(), fn ($a) => $a->type === AdjustmentType::Shipping);

    expect($shipping)->not->toBeEmpty()
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(1000);
});

it('defaults a promotion stacking policy from config', function (): void {
    config()->set('commerce.pricing.stacking', 'best_of');

    $promotion = new Promotion(['name' => 'No stacking declared', 'actions' => []]);
    $promotion->save();

    expect($promotion->fresh()->stacking)->toBe(StackingPolicy::BestOf);
});

it('records a PromotionApplied event on placement', function (): void {
    Promotion::factory()->create(['name' => 'P', 'actions' => [['type' => 'percentage_off', 'percent' => 10]]]);
    [$cart] = cartSubtotal($this->carts, 1000, 1);

    app(PlaceOrder::class)->handle($cart);

    expect(DomainEvent::query()->where('name', 'PromotionApplied')->exists())->toBeTrue();
});
