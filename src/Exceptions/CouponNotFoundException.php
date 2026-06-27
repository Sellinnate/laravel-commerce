<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CouponNotFoundException extends CommerceException
{
    public static function for(string $code): self
    {
        return new self("Coupon [{$code}] does not exist.");
    }
}
