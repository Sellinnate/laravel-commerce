<?php

declare(strict_types=1);

namespace Selli\Commerce\Support;

use Brick\Money\Money;
use Selli\Commerce\Contracts\PriceResolver;
use Selli\Commerce\Contracts\Purchasable;

/**
 * Core default: the price is whatever the purchasable declares. The Pricing
 * module replaces this with price-book resolution.
 */
final class DefaultPriceResolver implements PriceResolver
{
    public function resolve(Purchasable $purchasable, string $currency, array $context = []): Money
    {
        return $purchasable->getUnitPrice($currency);
    }
}
