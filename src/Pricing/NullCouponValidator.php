<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing;

use Selli\Commerce\Contracts\CouponValidator;
use Selli\Commerce\Exceptions\PricingModuleDisabledException;

/**
 * Bound when the Pricing module is disabled: any attempt to apply a coupon
 * fails loudly rather than silently doing nothing.
 */
final class NullCouponValidator implements CouponValidator
{
    public function validate(string $code, array $context = []): void
    {
        throw PricingModuleDisabledException::make();
    }
}
