<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CouponInactiveException extends CommerceException
{
    public static function for(string $code): self
    {
        return new self("Coupon [{$code}] is not active.");
    }
}
