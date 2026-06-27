<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

use Brick\Money\Money;

final class CouponMinimumNotMetException extends CommerceException
{
    public static function for(string $code, Money $minimum): self
    {
        return new self(sprintf('Coupon [%s] requires a minimum of %s.', $code, (string) $minimum));
    }
}
