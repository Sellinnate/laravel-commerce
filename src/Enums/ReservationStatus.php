<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

enum ReservationStatus: string
{
    /** Holding stock; counts against available-to-promise. */
    case Active = 'active';

    /** Given back before consumption (removed line, TTL elapsed, cancelled). */
    case Released = 'released';

    /** Turned into a shipment when its order was placed. */
    case Consumed = 'consumed';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
