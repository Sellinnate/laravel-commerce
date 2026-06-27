<?php

declare(strict_types=1);

use Brick\Money\Context\AutoContext;
use Brick\Money\Money;
use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Calculation\CalculationLine;
use Selli\Commerce\Calculation\Calculators\GrandTotalCalculator;
use Selli\Commerce\Calculation\Pipeline;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Support\DefaultRoundingStrategy;

it('runs calculators in order and consolidates the grand total', function (): void {
    $tenPercentOff = new class implements Calculator
    {
        public function apply(Calculation $calculation): void
        {
            $discount = $calculation->itemsSubtotal()->multipliedBy('0.10');
            $calculation->addAdjustment(new Adjustment(
                AdjustmentType::Discount,
                '10% off',
                $discount->multipliedBy(-1),
                $this->identifier(),
            ));
        }

        public function identifier(): string
        {
            return 'ten_percent_off';
        }
    };

    $pipeline = new Pipeline([$tenPercentOff, new GrandTotalCalculator(new DefaultRoundingStrategy)]);

    $calc = new Calculation('EUR');
    $calc->addLine(new CalculationLine('1', 'product', 'p1', 'Item', 1, Money::ofMinor(1000, 'EUR')));

    $pipeline->process($calc);

    expect($calc->grandTotal()->getMinorAmount()->toInt())->toBe(900)
        ->and($pipeline->calculators())->toHaveCount(2);
});

it('supports fluent piping', function (): void {
    $pipeline = (new Pipeline)->pipe(new GrandTotalCalculator(new DefaultRoundingStrategy));

    expect($pipeline->calculators())->toHaveCount(1);
});

it('rounds half up to the currency scale', function (): void {
    $rounding = new DefaultRoundingStrategy;
    $rounded = $rounding->round(Money::of('10.005', 'EUR', new AutoContext));

    expect($rounded->getMinorAmount()->toInt())->toBe(1001);
});
