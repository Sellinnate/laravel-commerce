<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CartItemMismatchException extends CommerceException
{
    public static function notInCart(string $itemId, string $cartId): self
    {
        return new self("Cart item [{$itemId}] does not belong to cart [{$cartId}].");
    }
}
