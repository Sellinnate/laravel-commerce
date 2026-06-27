<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class CurrencyMismatchException extends CommerceException
{
    public static function between(string $expected, string $actual): self
    {
        return new self("Currency mismatch: cart operates in {$expected} but {$actual} was given.");
    }
}
