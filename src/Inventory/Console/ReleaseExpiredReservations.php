<?php

declare(strict_types=1);

namespace Selli\Commerce\Inventory\Console;

use Illuminate\Console\Command;
use Selli\Commerce\Inventory\InventoryManager;

/**
 * Releases stock reservations whose TTL has elapsed, returning their held
 * quantity to available-to-promise. Schedule it (e.g. every minute) so abandoned
 * carts free their stock automatically.
 */
final class ReleaseExpiredReservations extends Command
{
    protected $signature = 'commerce:inventory:release-expired';

    protected $description = 'Release stock reservations whose TTL has elapsed.';

    public function handle(InventoryManager $inventory): int
    {
        $released = $inventory->releaseExpired();

        $this->info("Released {$released} expired reservation(s).");

        return self::SUCCESS;
    }
}
