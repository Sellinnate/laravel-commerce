<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

final class Completed extends OrderState
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'Completed';
    }
}
