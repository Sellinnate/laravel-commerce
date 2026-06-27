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

        if ($amount->isZero()) {
            return array_fill(0, $count, $amount->multipliedBy(0));
        }

        // No positive weight to spread across (e.g. every line subtotal is
        // zero): split equally so a non-zero amount is never silently dropped
        // and the parts still sum to the whole. This is deliberately gated on
        // "no positive weight" rather than "weights sum to zero", so a mixed set
        // like [100, -100] is NOT flattened to an equal split — it falls through
        // to Brick, which rejects the negative ratio loudly.
        if (array_filter($weights, static fn (int $w): bool => $w > 0) === []) {
            $weights = array_fill(0, $count, 1);
        }

        return array_values($amount->allocate(...$weights));
    }
}
