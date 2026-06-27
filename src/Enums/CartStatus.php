<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case Merged = 'merged';
    case Converted = 'converted';
    case Abandoned = 'abandoned';
    case Expired = 'expired';

    public function isMutable(): bool
    {
        return $this === self::Active;
    }
}
