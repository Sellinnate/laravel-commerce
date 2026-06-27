<?php

declare(strict_types=1);

namespace Selli\Commerce\Calculation\Calculators;

use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\RoundingStrategy;

/**
 * Consolidates every contribution into the final, rounded grand total.
 * Rounding happens here and only here, so line and total can never drift.
 */
final class GrandTotalCalculator implements Calculator
{
    public function __construct(
        private readonly RoundingStrategy $rounding,
    ) {}

    public function apply(Calculation $calculation): void
    {
        $calculation->setGrandTotal(
            $this->rounding->round($calculation->rawGrandTotal()),
        );
    }

    public function identifier(): string
    {
        return 'grand_total';
    }
}
