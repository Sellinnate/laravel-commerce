<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class EmptyCartException extends CommerceException
{
    public static function cannotPlaceOrder(): self
    {
        return new self('Cannot place an order from an empty cart.');
    }
}
