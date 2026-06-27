<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CouponExpiredException extends CommerceException
{
    public static function expired(string $code): self
    {
        return new self("Coupon [{$code}] has expired.");
    }

    public static function notYetValid(string $code): self
    {
        return new self("Coupon [{$code}] is not valid yet.");
    }
}
