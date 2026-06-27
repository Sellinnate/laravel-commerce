<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Brick\Money\Money;

/**
 * Centralises every rounding decision so riga and totale can never drift by a
 * cent. Per-currency aware (the yen has no minor unit, the dinar has three).
 */
interface RoundingStrategy
{
    /**
     * Round a possibly higher-scale monetary amount to the currency's
     * canonical scale.
     */
    public function round(Money $money): Money;
}
