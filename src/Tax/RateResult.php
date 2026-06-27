<?php

declare(strict_types=1);

namespace Selli\Commerce\Tax;

use InvalidArgumentException;

/**
 * The resolved tax rate for a category in a jurisdiction: a rate in basis points
 * (2200 = 22.00%) and a human label for the breakdown.
 */
final class RateResult
{
    public function __construct(
        public readonly int $basisPoints,
        public readonly string $label,
    ) {
        // TaxResolver is an extension seam: a custom resolver must not be able to
        // produce negative tax, and -10000 bps would divide by zero in the
        // inclusive branch of TaxCalculator (amount × rate ÷ (10000 + rate)).
        if ($basisPoints < 0) {
            throw new InvalidArgumentException(
                "Tax rate basis points must be zero or positive, got {$basisPoints}.",
            );
        }
    }

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }
}
