<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Brick\Money\Money;

/**
 * Resolves the effective unit price of a purchasable for a currency.
 *
 * The core default simply asks the {@see Purchasable}. The Pricing module
 * overrides this to consult price books, customer segments and validity
 * windows; any project can substitute its own resolver.
 */
interface PriceResolver
{
    /**
     * @param  array<string, mixed>  $context  Arbitrary resolution context
     *                                         (tenant, customer, segment, quantity).
     */
    public function resolve(Purchasable $purchasable, string $currency, array $context = []): Money;
}
