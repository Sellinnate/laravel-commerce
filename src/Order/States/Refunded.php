<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

final class Refunded extends OrderState
{
    public static string $name = 'refunded';

    public function label(): string
    {
        return 'Refunded';
    }

    public function isFinal(): bool
    {
        return true;
    }
}
