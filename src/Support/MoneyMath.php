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

    /**
     * Allocate $amount across $weights so the parts sum EXACTLY to the whole,
     * distributing any rounding remainder deterministically (the leftover minor
     * units land on the first weights). Returns a list of zeros — never a short
     * list — when the amount is zero or every weight is zero, so callers can zip
     * the result against their lines positionally. Negative amounts (discounts)
     * are supported; brick's allocate() rejects negative ratios, so weights must
     * be non-negative.
     *
     * @param  list<int>  $weights
     * @return list<Money>
     */
    public static function allocate(Money $amount, array $weights): array
    {
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        if ($amount->isZero() || array_sum($weights) === 0) {
            return array_fill(0, $count, $amount->multipliedBy(0));
        }

        return array_values($amount->allocate(...$weights));
    }
}
