<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

/**
 * How a promotion combines with others. Never let discounts stack by accident —
 * the behaviour is always an explicit, declared choice.
 */
enum StackingPolicy: string
{
    case Exclusive = 'exclusive';   // when applied, no other promotion may apply
    case Cumulative = 'cumulative'; // applies on top of others
    case BestOf = 'best_of';        // only the single best-value promotion applies
}
