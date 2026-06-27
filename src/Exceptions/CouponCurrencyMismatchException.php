<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CouponCurrencyMismatchException extends CommerceException
{
    public static function between(string $couponCurrency, string $cartCurrency): self
    {
        return new self("Coupon currency {$couponCurrency} does not match the cart currency {$cartCurrency}.");
    }
}
