<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class InvalidQuantityException extends CommerceException
{
    public static function mustBePositive(int $quantity): self
    {
        return new self("Quantity must be a positive integer, {$quantity} given.");
    }
}
