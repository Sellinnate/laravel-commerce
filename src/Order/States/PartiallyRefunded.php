<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

final class PartiallyRefunded extends OrderState
{
    public static string $name = 'partially_refunded';

    public function label(): string
    {
        return 'Partially refunded';
    }
}
