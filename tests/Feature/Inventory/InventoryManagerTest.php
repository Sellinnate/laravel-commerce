<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Selli\Commerce\Enums\ReservationStatus;
use Selli\Commerce\Enums\StockMovementType;
use Selli\Commerce\Events\Inventory\BackorderCreated;
use Selli\Commerce\Events\Inventory\StockDepleted;
use Selli\Commerce\Events\Inventory\StockReleased;
use Selli\Commerce\Events\Inventory\StockReserved;
use Selli\Commerce\Exceptions\InsufficientStockException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Inventory\InventoryManager;
use Selli\Commerce\Inventory\Models\StockMovement;
use Selli\Commerce\Inventory\Models\StockReservation;
use Selli\Commerce\Inventory\Models\Warehouse;

beforeEach(function (): void {
    $this->inventory = app(InventoryManager::class);
});

// setTestNow() mutates global state; always restore it so a failing time-travel
// assertion cannot leak the frozen clock into later tests.
afterEach(function (): void {
    Carbon::setTestNow();
});

it('records a receipt and reports available-to-promise', function (): void {
    $this->inventory->receive('product', 'p1', 10);

    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(10)
        ->and(StockMovement::where('purchasable_id', 'p1')->where('type', StockMovementType::Receipt)->sum('quantity'))->toBe(10);
});

it('returns null ATP for an untracked purchasable', function (): void {
    expect($this->inventory->availableToPromise('product', 'never-stocked', null))->toBeNull();
});

it('rebuilds on_hand as the sum of the ledger', function (): void {
    $this->inventory->receive('product', 'p1', 10);
    $this->inventory->adjust('product', 'p1', -3, reason: 'breakage');

    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(7)
        ->and((int) StockMovement::where('purchasable_id', 'p1')->sum('quantity'))->toBe(7);
});

it('holds stock against available-to-promise and releases it back', function (): void {
    $this->inventory->receive('product', 'p1', 10);

    $this->inventory->hold('cart-1', 'product', 'p1', 4, null);
    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(6);

    $this->inventory->release('commerce.cart', 'cart-1', null);
    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(10);
});

it('treats an expired hold as released for availability', function (): void {
    $this->inventory->receive('product', 'p1', 10);
    config()->set('commerce.inventory.reservation_ttl', 30);
    $this->inventory->hold('cart-1', 'product', 'p1', 4, null);

    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(6);

    Carbon::setTestNow(Carbon::now()->addHour());

    // The hold has lapsed: its stock is promised to someone else again.
    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(10);

    Carbon::setTestNow();
});

it('sweeps expired reservations', function (): void {
    $this->inventory->receive('product', 'p1', 10);
    config()->set('commerce.inventory.reservation_ttl', 30);
    $this->inventory->hold('cart-1', 'product', 'p1', 4, null);

    Carbon::setTestNow(Carbon::now()->addHour());
    $released = $this->inventory->releaseExpired();
    Carbon::setTestNow();

    expect($released)->toBe(1)
        ->and(StockReservation::where('reference_id', 'cart-1')->first()?->status)->toBe(ReservationStatus::Released);
});

it('updates an existing hold absolutely rather than stacking', function (): void {
    $this->inventory->receive('product', 'p1', 10);

    $this->inventory->hold('cart-1', 'product', 'p1', 4, null);
    $this->inventory->hold('cart-1', 'product', 'p1', 6, null); // absolute, not +6

    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(4)
        ->and(StockReservation::where('reference_id', 'cart-1')->where('status', ReservationStatus::Active->value)->count())->toBe(1);
});

it('fulfils an order by shipping stock under the ledger', function (): void {
    $this->inventory->receive('product', 'p1', 10);

    $backordered = $this->inventory->fulfillOrder('order-1', [
        ['type' => 'product', 'id' => 'p1', 'quantity' => 3, 'name' => 'P1'],
    ], null);

    expect($backordered)->toBe([])
        ->and($this->inventory->availableToPromise('product', 'p1', null))->toBe(7)
        ->and(StockMovement::where('purchasable_id', 'p1')->where('type', StockMovementType::Shipment)->sum('quantity'))->toBe(-3);
});

it('throws when short of stock and backorder is denied', function (): void {
    config()->set('commerce.inventory.backorder', 'deny');
    $this->inventory->receive('product', 'p1', 2);

    $this->inventory->fulfillOrder('order-1', [
        ['type' => 'product', 'id' => 'p1', 'quantity' => 5, 'name' => 'P1'],
    ], null);
})->throws(InsufficientStockException::class);

it('allows a backorder and reports it when the policy permits', function (): void {
    Event::fake([BackorderCreated::class]);
    config()->set('commerce.inventory.backorder', 'allow');
    $inventory = app(InventoryManager::class);
    $inventory->receive('product', 'p1', 2);

    $backordered = $inventory->fulfillOrder('order-1', [
        ['type' => 'product', 'id' => 'p1', 'quantity' => 5, 'name' => 'P1'],
    ], null);

    expect($backordered)->toBe([['type' => 'product', 'id' => 'p1', 'quantity' => 3]])
        ->and($inventory->availableToPromise('product', 'p1', null))->toBe(-3);
    Event::assertDispatched(BackorderCreated::class);
});

it('consumes a cart hold when fulfilling that cart\'s order', function (): void {
    $this->inventory->receive('product', 'p1', 10);
    $this->inventory->hold('cart-1', 'product', 'p1', 3, null);

    // ATP already reflects the hold.
    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(7);

    $this->inventory->fulfillOrder('order-1', [
        ['type' => 'product', 'id' => 'p1', 'quantity' => 3, 'name' => 'P1'],
    ], null, 'cart-1');

    // The hold became a shipment: on_hand 7, no lingering reservation.
    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(7)
        ->and(StockReservation::where('reference_id', 'cart-1')->where('status', ReservationStatus::Active->value)->count())->toBe(0);
});

it('releases the unused remainder of a partially consumed hold', function (): void {
    $this->inventory->receive('product', 'p1', 10);
    $this->inventory->hold('cart-1', 'product', 'p1', 5, null); // hold 5

    // The order only needs 3 of the 5 held: 3 ship, 2 return to available.
    $this->inventory->fulfillOrder('order-1', [
        ['type' => 'product', 'id' => 'p1', 'quantity' => 3, 'name' => 'P1'],
    ], null, 'cart-1');

    // on_hand 7, no orphaned reserved: ATP is the full 7, not 5.
    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(7)
        ->and(StockReservation::where('reference_id', 'cart-1')->where('status', ReservationStatus::Active->value)->count())->toBe(0);
});

it('atomically refuses a hold that would oversell under the lock', function (): void {
    config()->set('commerce.inventory.backorder', 'deny');
    $this->inventory->receive('product', 'p1', 2);
    $this->inventory->hold('cart-1', 'product', 'p1', 2, null); // holds all

    // A hold for another cart beyond ATP is refused at the row lock, with the
    // same exception the cart surfaces, even though an advisory pre-check might
    // have passed concurrently.
    expect(fn () => $this->inventory->hold('cart-2', 'product', 'p1', 1, null))
        ->toThrow(ProductNotAvailableException::class)
        ->and($this->inventory->availableToPromise('product', 'p1', null))->toBe(0);
});

it('excludes inactive warehouses from available-to-promise', function (): void {
    // Stock that lives only in a disabled warehouse cannot be promised, because
    // fulfilment never ships from it.
    Warehouse::create(['code' => 'closed', 'name' => 'Closed', 'active' => false]);
    $this->inventory->receive('product', 'p1', 10, warehouseCode: 'closed');

    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(0);

    // Stock in an active warehouse counts.
    $this->inventory->receive('product', 'p1', 4);
    expect($this->inventory->availableToPromise('product', 'p1', null))->toBe(4);
});

it('is idempotent across overlapping expired sweeps', function (): void {
    $this->inventory->receive('product', 'p1', 10);
    config()->set('commerce.inventory.reservation_ttl', 30);
    $this->inventory->hold('cart-1', 'product', 'p1', 4, null);
    Carbon::setTestNow(Carbon::now()->addHour());

    expect($this->inventory->releaseExpired())->toBe(1)
        ->and($this->inventory->releaseExpired())->toBe(0) // already released, not re-counted
        ->and($this->inventory->availableToPromise('product', 'p1', null))->toBe(10); // not double-decremented
    Carbon::setTestNow();
});

it('isolates stock between tenants', function (): void {
    $this->inventory->receive('product', 'p1', 10, tenantId: 'tenant-a');

    expect($this->inventory->availableToPromise('product', 'p1', 'tenant-a'))->toBe(10)
        ->and($this->inventory->availableToPromise('product', 'p1', 'tenant-b'))->toBeNull()
        ->and($this->inventory->availableToPromise('product', 'p1', null))->toBeNull();
});

it('scopes reservation lookups to the tenant', function (): void {
    $this->inventory->receive('product', 'p1', 10, tenantId: 'tenant-a');
    $this->inventory->receive('product', 'p1', 10, tenantId: 'tenant-b');

    // The same cart id exists in both tenants (an id collision).
    $this->inventory->hold('cart-x', 'product', 'p1', 4, 'tenant-a');
    $this->inventory->hold('cart-x', 'product', 'p1', 4, 'tenant-b');

    // Releasing tenant A's hold must leave tenant B's untouched.
    $this->inventory->release('commerce.cart', 'cart-x', 'tenant-a');

    expect($this->inventory->availableToPromise('product', 'p1', 'tenant-a'))->toBe(10)
        ->and($this->inventory->availableToPromise('product', 'p1', 'tenant-b'))->toBe(6);
});

it('dispatches reserve and release events', function (): void {
    Event::fake([StockReserved::class, StockReleased::class]);
    $inventory = app(InventoryManager::class);
    $inventory->receive('product', 'p1', 10);

    $inventory->hold('cart-1', 'product', 'p1', 2, null);
    $inventory->release('commerce.cart', 'cart-1', null);

    Event::assertDispatched(StockReserved::class);
    Event::assertDispatched(StockReleased::class);
});

it('dispatches StockDepleted when on_hand reaches zero', function (): void {
    Event::fake([StockDepleted::class]);
    $inventory = app(InventoryManager::class);
    $inventory->receive('product', 'p1', 3);

    $inventory->fulfillOrder('order-1', [
        ['type' => 'product', 'id' => 'p1', 'quantity' => 3, 'name' => 'P1'],
    ], null);

    Event::assertDispatched(StockDepleted::class);
});
