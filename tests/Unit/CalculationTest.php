<?php

declare(strict_types=1);

use Brick\Money\Money;
use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Calculation\CalculationLine;
use Selli\Commerce\Enums\AdjustmentType;

function eurLine(string $id, int $amount, int $qty = 1): CalculationLine
{
    return new CalculationLine($id, 'product', 'p'.$id, 'Item '.$id, $qty, Money::ofMinor($amount, 'EUR'));
}

it('sums line subtotals into the grand total', function (): void {
    $calc = new Calculation('EUR');
    $calc->addLine(eurLine('1', 500, 2));
    $calc->addLine(eurLine('2', 1000));

    expect($calc->itemsSubtotal()->getMinorAmount()->toInt())->toBe(2000)
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(2000);
});

it('returns zero for an empty calculation', function (): void {
    $calc = new Calculation('EUR');

    expect($calc->isEmpty())->toBeTrue()
        ->and($calc->grandTotal()->getMinorAmount()->toInt())->toBe(0);
});

it('applies negative discount adjustments to the total', function (): void {
    $calc = new Calculation('EUR');
    $calc->addLine(eurLine('1', 1000));
    $calc->addAdjustment(new Adjustment(AdjustmentType::Discount, 'WELCOME10', Money::ofMinor(-100, 'EUR'), 'coupon'));

    expect($calc->rawGrandTotal()->getMinorAmount()->toInt())->toBe(900)
        ->and($calc->discountTotal()->getMinorAmount()->toInt())->toBe(-100);
});

it('never adds inclusive tax to the total twice', function (): void {
    $calc = new Calculation('EUR');
    $calc->addLine(eurLine('1', 1220));
    $calc->addAdjustment(new Adjustment(AdjustmentType::Tax, 'VAT 22% (incl.)', Money::ofMinor(220, 'EUR'), 'tax', affectsTotal: false));

    expect($calc->rawGrandTotal()->getMinorAmount()->toInt())->toBe(1220)
        ->and($calc->taxTotal()->getMinorAmount()->toInt())->toBe(220);
});

it('invariant: items subtotal plus total-affecting adjustments equals grand total', function (): void {
    $calc = new Calculation('EUR');
    $calc->addLine(eurLine('1', 999, 3));
    $calc->addLine(eurLine('2', 1, 7));
    $calc->addAdjustment(new Adjustment(AdjustmentType::Discount, 'D', Money::ofMinor(-250, 'EUR'), 'x'));
    $calc->addAdjustment(new Adjustment(AdjustmentType::Tax, 'T', Money::ofMinor(180, 'EUR'), 'tax'));

    $expected = $calc->itemsSubtotal()->plus($calc->adjustmentsTotal());

    expect($calc->rawGrandTotal()->isEqualTo($expected))->toBeTrue();
});

it('produces an explainable breakdown', function (): void {
    $calc = new Calculation('EUR');
    $calc->addLine(eurLine('1', 1000, 2));
    $calc->addAdjustment(new Adjustment(AdjustmentType::Discount, 'D', Money::ofMinor(-200, 'EUR'), 'x'));

    $breakdown = $calc->breakdown();

    expect($breakdown['items_subtotal'])->toBe(2000)
        ->and($breakdown['discount_total'])->toBe(-200)
        ->and($breakdown['currency'])->toBe('EUR')
        ->and($breakdown['lines'])->toHaveCount(1);
});
