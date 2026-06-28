<?php

declare(strict_types=1);

namespace Selli\Commerce\Enums;

use Illuminate\Support\Facades\Config;

enum ReservationTiming: string
{
    /** Stock is reserved only when the order is placed (default, cheapest). */
    case PlaceOrder = 'place_order';

    /** Stock is held with a TTL the moment a line is added to the cart. */
    case AddToCart = 'add_to_cart';

    public static function fromConfig(): self
    {
        return self::tryFrom(Config::string('commerce.inventory.reserve_on', 'place_order')) ?? self::PlaceOrder;
    }
}
