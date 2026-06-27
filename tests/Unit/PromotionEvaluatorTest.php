<?php

declare(strict_types=1);

use Brick\Money\Money;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Calculation\CalculationLine;
use Selli\Commerce\Pricing\Models\Promotion;
use Selli\Commerce\Pricing\PromotionEvaluator;

function calcWithLine(int $amount, int $quantity = 1): Calculation
{
    $calc = new Calculation('EUR');
    $calc->addLine(new CalculationLine('1', 'product', 'p1', 'Item', $quantity, Money::ofMinor($amount, 'EUR')));

    return $calc;
}

it('matches a cart subtotal minimum condition', function (): void {
    $evaluator = new PromotionEvaluator;
    $promo = new Promotion([
        'conditions' => [['type' => 'cart_subtotal_min', 'amount' => 1000, 'currency' => 'EUR']],
        'actions' => [['type' => 'percentage_off', 'percent' => 10]],
    ]);

    expect($evaluator->matches($promo, calcWithLine(600, 2)))->toBeTrue()
        ->and($evaluator->matches($promo, calcWithLine(600, 1)))->toBeFalse();
});

it('matches an item quantity minimum condition', function (): void {
    $evaluator = new PromotionEvaluator;
    $promo = new Promotion(['conditions' => [['type' => 'item_quantity_min', 'quantity' => 3]], 'actions' => []]);

    expect($evaluator->matches($promo, calcWithLine(100, 3)))->toBeTrue()
        ->and($evaluator->matches($promo, calcWithLine(100, 2)))->toBeFalse();
});

it('matches a has-purchasable condition', function (): void {
    $evaluator = new PromotionEvaluator;
    $promo = new Promotion(['conditions' => [['type' => 'has_purchasable', 'purchasable_type' => 'product', 'purchasable_id' => 'p1']], 'actions' => []]);

    expect($evaluator->matches($promo, calcWithLine(100)))->toBeTrue();
});

it('computes a percentage discount', function (): void {
    $evaluator = new PromotionEvaluator;
    $promo = new Promotion(['conditions' => [], 'actions' => [['type' => 'percentage_off', 'percent' => 10]]]);
    $calc = calcWithLine(600, 2); // subtotal 1200

    expect($evaluator->discount($promo, $calc, $calc->itemsSubtotal())->getMinorAmount()->toInt())->toBe(120);
});

it('computes a fixed discount capped at the base', function (): void {
    $evaluator = new PromotionEvaluator;
    $promo = new Promotion(['conditions' => [], 'actions' => [['type' => 'fixed_off', 'amount' => 5000, 'currency' => 'EUR']]]);
    $calc = calcWithLine(1000);

    expect($evaluator->discount($promo, $calc, $calc->itemsSubtotal())->getMinorAmount()->toInt())->toBe(1000);
});

it('detects a free-shipping action', function (): void {
    $evaluator = new PromotionEvaluator;
    $promo = new Promotion(['conditions' => [], 'actions' => [['type' => 'free_shipping']]]);

    expect($evaluator->grantsFreeShipping($promo))->toBeTrue();
});
