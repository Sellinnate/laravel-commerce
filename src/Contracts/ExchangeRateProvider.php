<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Brick\Math\BigNumber;

/**
 * Supplies FX rates. The core imposes no source; once an order's currency is
 * fixed, totals are computed and frozen in that currency.
 */
interface ExchangeRateProvider
{
    /**
     * The rate to multiply an amount in $from to obtain an amount in $to.
     */
    public function rate(string $from, string $to): BigNumber;
}
