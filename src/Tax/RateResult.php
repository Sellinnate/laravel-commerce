<?php

declare(strict_types=1);

namespace Selli\Commerce\Tax;

/**
 * The resolved tax rate for a category in a jurisdiction: a rate in basis points
 * (2200 = 22.00%) and a human label for the breakdown.
 */
final class RateResult
{
    public function __construct(
        public readonly int $basisPoints,
        public readonly string $label,
    ) {}

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }
}
