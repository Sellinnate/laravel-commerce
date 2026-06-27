<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

final class Confirmed extends OrderState
{
    public static string $name = 'confirmed';

    public function label(): string
    {
        return 'Confirmed';
    }
}
