<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class InsufficientStockException extends CommerceException
{
    public static function for(string $name, int $requested, int $available): self
    {
        return new self(
            "Insufficient stock for \"{$name}\": requested {$requested}, only {$available} available."
        );
    }
}
