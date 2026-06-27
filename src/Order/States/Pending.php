<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

final class Pending extends OrderState
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending';
    }
}
