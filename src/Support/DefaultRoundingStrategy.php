<?php

declare(strict_types=1);

namespace Selli\Commerce\Support;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Selli\Commerce\Contracts\RoundingStrategy;

/**
 * Rounds to each currency's canonical scale using a configurable rounding
 * mode (default HALF_UP). Per-currency aware via brick/money.
 */
final class DefaultRoundingStrategy implements RoundingStrategy
{
    public function __construct(
        private readonly RoundingMode $mode = RoundingMode::HalfUp,
    ) {}

    public function round(Money $money): Money
    {
        // Convert to the currency's canonical scale (DefaultContext), applying
        // the configured rounding mode to any sub-unit fractions.
        return $money->to(new DefaultContext, $this->mode);
    }
}
