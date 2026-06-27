<?php

declare(strict_types=1);

namespace Selli\Commerce\Support;

use Brick\Money\Money;
use Selli\Commerce\Contracts\RoundingStrategy;

/**
 * Small, exact helpers for summing collections of {@see Money}. Addition of
 * exact decimals never needs rounding, so no rounding mode is involved here —
 * rounding is the sole responsibility of the {@see RoundingStrategy}.
 */
final class MoneyMath
{
    /**
     * Sum any number of money values, returning zero in $currency when empty.
     *
     * @param  iterable<Money>  $moneys
     */
    public static function sum(string $currency, iterable $moneys): Money
    {
        $total = Money::zero($currency);

        foreach ($moneys as $money) {
            $total = $total->plus($money);
        }

        return $total;
    }
}
