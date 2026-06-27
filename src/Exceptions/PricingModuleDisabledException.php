<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class PricingModuleDisabledException extends CommerceException
{
    public static function make(): self
    {
        return new self('The Pricing module is disabled; coupons and promotions are unavailable.');
    }
}
