<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CouponUsageLimitReachedException extends CommerceException
{
    public static function for(string $code): self
    {
        return new self("Coupon [{$code}] has reached its usage limit.");
    }

    public static function forCustomer(string $code): self
    {
        return new self("Coupon [{$code}] has reached its per-customer usage limit.");
    }

    public static function requiresIdentification(string $code): self
    {
        return new self("Coupon [{$code}] is limited per customer and requires an identified customer.");
    }
}
