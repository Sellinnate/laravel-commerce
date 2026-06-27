<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CartNotFoundException extends CommerceException
{
    public static function forPlacement(string $cartId): self
    {
        return new self("Cart [{$cartId}] no longer exists and cannot be placed.");
    }
}
