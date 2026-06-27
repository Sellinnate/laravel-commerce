<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Selli\Commerce\Calculation\Calculation;

/**
 * A single, ordered step of the deterministic calculation pipeline.
 *
 * Each calculator receives the mutable {@see Calculation} accumulator and
 * contributes a named, traceable adjustment. Order is configurable; the total
 * is always a pure, repeatable function of the lines and the active rules.
 */
interface Calculator
{
    /**
     * Apply this calculator's contribution to the calculation in place.
     */
    public function apply(Calculation $calculation): void;

    /**
     * Stable identifier used in the breakdown and for ordering/debugging.
     */
    public function identifier(): string;
}
