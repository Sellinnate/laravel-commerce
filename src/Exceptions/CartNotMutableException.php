<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

use Selli\Commerce\Enums\CartStatus;

final class CartNotMutableException extends CommerceException
{
    public static function inStatus(CartStatus $status): self
    {
        return new self("The cart cannot be modified while in status \"{$status->value}\".");
    }
}
