<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

use Illuminate\Support\Facades\Config;

enum BackorderPolicy: string
{
    /** Refuse to sell below available stock (throws InsufficientStockException). */
    case Deny = 'deny';

    /** Allow selling below zero; the order is annotated as a backorder. */
    case Allow = 'allow';

    public function allowsBackorder(): bool
    {
        return $this === self::Allow;
    }

    public static function fromConfig(): self
    {
        return self::tryFrom(Config::string('commerce.inventory.backorder', 'deny')) ?? self::Deny;
    }
}
