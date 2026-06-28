<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

/**
 * The kinds of movement recorded in the append-only stock ledger. `on_hand` is
 * the running sum of {@see affectsOnHand()} movements; `reserved` is the running
 * sum of {@see affectsReserved()} movements.
 */
enum StockMovementType: string
{
    /** Goods received into the warehouse (+on_hand). */
    case Receipt = 'receipt';

    /** Manual rectification of the counted quantity (±on_hand). */
    case Adjustment = 'adjustment';

    /** Stock promised to a cart/order, not yet shipped (+reserved). */
    case Reservation = 'reservation';

    /** A reservation given back (e.g. cart abandoned, TTL elapsed) (−reserved). */
    case Release = 'release';

    /** Reserved stock leaving on a placed order (−on_hand, −reserved). */
    case Shipment = 'shipment';

    public function affectsOnHand(): bool
    {
        return $this === self::Receipt
            || $this === self::Adjustment
            || $this === self::Shipment;
    }

    public function affectsReserved(): bool
    {
        return $this === self::Reservation
            || $this === self::Release
            || $this === self::Shipment;
    }
}
