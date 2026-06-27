<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class OrderNotFoundException extends CommerceException
{
    public static function forTransition(string $orderId): self
    {
        return new self("Order [{$orderId}] no longer exists and cannot be transitioned.");
    }
}
