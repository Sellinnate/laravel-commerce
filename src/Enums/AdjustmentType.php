<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

/**
 * Classifies each contribution accumulated in the calculation breakdown.
 */
enum AdjustmentType: string
{
    case Subtotal = 'subtotal';
    case Promotion = 'promotion';
    case Discount = 'discount';
    case Shipping = 'shipping';
    case Tax = 'tax';
    case GiftCard = 'gift_card';
    case Fee = 'fee';

    /**
     * Whether the adjustment reduces the payable total.
     */
    public function isNegative(): bool
    {
        return match ($this) {
            self::Promotion, self::Discount, self::GiftCard => true,
            default => false,
        };
    }
}
