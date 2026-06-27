<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

final class Processing extends OrderState
{
    public static string $name = 'processing';

    public function label(): string
    {
        return 'Processing';
    }
}
