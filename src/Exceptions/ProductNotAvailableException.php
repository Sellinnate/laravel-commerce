<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class ProductNotAvailableException extends CommerceException
{
    public static function for(string $name, int $quantity): self
    {
        return new self("\"{$name}\" is not available in the requested quantity ({$quantity}).");
    }
}
