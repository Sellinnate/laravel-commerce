<?php

declare(strict_types=1);

use Selli\Commerce\Audit\Models\DomainEvent;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Contracts\GiftCardValidator;
use Selli\Commerce\Enums\GiftCardTransactionType;
use Selli\Commerce\Exceptions\GiftCardException;
use Selli\Commerce\Exceptions\PricingModuleDisabledException;
use Selli\Commerce\Order\Actions\PlaceOrder;
use Selli\Commerce\Pricing\Models\GiftCard;
use Selli\Commerce\Pricing\Models\GiftCardTransaction;
use Selli\Commerce\Pricing\NullGiftCardValidator;
use Selli\Commerce\Tests\Fixtures\Product;

beforeEach(function (): void {
    $this->carts = app(CartManager::class);
});

function cartFor(CartManager $carts, int $priceCents, int $quantity = 1): Cart
{
    $product = Product::create(['name' => 'Widget', 'price_cents' => $priceCents]);
    $cart = $carts->create('EUR');
    $carts->add($cart, $product, $quantity);

    return $cart;
}

it('applies a gift card as a tender capped at the total', function (): void {
    GiftCard::factory()->create(['code' => 'GIFT50', 'initial_amount' => 5000, 'balance' => 5000]);
    $cart = cartFor($this->carts, 3000, 1); // total 3000, gift covers it fully

    $this->carts->applyGiftCard($cart, 'GIFT50');

    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(0)
        ->and($this->carts->giftCards($cart))->toBe(['GIFT50']);
});

it('applies only the gift card balance when it is smaller than the total', function (): void {
    GiftCard::factory()->create(['code' => 'GIFT10', 'initial_amount' => 1000, 'balance' => 1000]);
    $cart = cartFor($this->carts, 3000, 1);

    $this->carts->applyGiftCard($cart, 'GIFT10');

    expect($this->carts->calculate($cart)->grandTotal()->getMinorAmount()->toInt())->toBe(2000);
});

it('rejects an unknown gift card', function (): void {
    $cart = cartFor($this->carts, 1000);
    $this->carts->applyGiftCard($cart, 'NONE');
})->throws(GiftCardException::class);

it('rejects a gift card in a different currency', function (): void {
    GiftCard::factory()->create(['code' => 'USDGC', 'currency' => 'USD', 'balance' => 1000, 'initial_amount' => 1000]);
    $cart = cartFor($this->carts, 1000);
    $this->carts->applyGiftCard($cart, 'USDGC');
})->throws(GiftCardException::class);

it('decrements the balance and ledgers the redemption on placement', function (): void {
    $giftCard = GiftCard::factory()->create(['code' => 'GIFT10', 'initial_amount' => 1000, 'balance' => 1000]);
    $cart = cartFor($this->carts, 3000, 1);
    $this->carts->applyGiftCard($cart, 'GIFT10');

    $order = app(PlaceOrder::class)->handle($cart);

    expect($giftCard->fresh()->balance)->toBe(0)
        ->and(GiftCardTransaction::query()
            ->where('gift_card_id', $giftCard->id)
            ->where('type', GiftCardTransactionType::Redeem->value)
            ->where('amount', 1000)
            ->where('order_id', $order->id)
            ->exists())->toBeTrue();
});

it('emits a GiftCardRedeemed event on placement', function (): void {
    GiftCard::factory()->create(['code' => 'GIFT10', 'initial_amount' => 1000, 'balance' => 1000]);
    $cart = cartFor($this->carts, 3000, 1);
    $this->carts->applyGiftCard($cart, 'GIFT10');

    app(PlaceOrder::class)->handle($cart);

    expect(DomainEvent::query()->where('name', 'GiftCardRedeemed')->exists())->toBeTrue();
});

it('refuses gift cards when the pricing module is disabled', function (): void {
    config()->set('commerce.modules.pricing', false);
    $carts = app(CartManager::class);
    $cart = cartFor($carts, 1000);

    expect($carts)->toBeInstanceOf(CartManager::class)
        ->and(app(GiftCardValidator::class))->toBeInstanceOf(NullGiftCardValidator::class);

    $carts->applyGiftCard($cart, 'X');
})->throws(PricingModuleDisabledException::class);
