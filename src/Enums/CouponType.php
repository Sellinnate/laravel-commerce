<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
