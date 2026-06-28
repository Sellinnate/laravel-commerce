<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Selli\Commerce\Enums\ReservationStatus;
use Selli\Commerce\Inventory\InventoryManager;
use Selli\Commerce\Inventory\Models\StockReservation;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('releases expired reservations from the console', function (): void {
    $inventory = app(InventoryManager::class);
    $inventory->receive('product', 'p1', 10);
    config()->set('commerce.inventory.reservation_ttl', 30);
    $inventory->hold('cart-1', 'product', 'p1', 4, null);

    Carbon::setTestNow(Carbon::now()->addHour());

    $this->artisan('commerce:inventory:release-expired')
        ->expectsOutputToContain('Released 1 expired reservation(s).')
        ->assertSuccessful();

    Carbon::setTestNow();

    expect(StockReservation::where('reference_id', 'cart-1')->first()?->status)->toBe(ReservationStatus::Released);
});
