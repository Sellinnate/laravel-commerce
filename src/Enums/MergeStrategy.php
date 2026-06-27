<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

enum MergeStrategy: string
{
    case KeepHighestQuantity = 'keep_highest_quantity';
    case Sum = 'sum';
    case Replace = 'replace';
}
