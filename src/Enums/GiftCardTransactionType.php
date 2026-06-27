<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

enum GiftCardTransactionType: string
{
    case Issue = 'issue';
    case Redeem = 'redeem';
    case Refund = 'refund';
}
