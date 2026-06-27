<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

final class Cancelled extends OrderState
{
    public static string $name = 'cancelled';

    public function label(): string
    {
        return 'Cancelled';
    }

    public function isFinal(): bool
    {
        return true;
    }
}
