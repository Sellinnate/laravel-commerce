<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Selli\Commerce\Enums\BackorderPolicy;
use Selli\Commerce\Enums\ReservationStatus;
use Selli\Commerce\Enums\StockMovementType;
use Selli\Commerce\Inventory\Models\StockItem;
use Selli\Commerce\Inventory\Models\StockReservation;
use Selli\Commerce\Inventory\NullInventory;

it('classifies movement effects on hand and reserved', function (): void {
    expect(StockMovementType::Receipt->affectsOnHand())->toBeTrue()
        ->and(StockMovementType::Receipt->affectsReserved())->toBeFalse()
        ->and(StockMovementType::Reservation->affectsReserved())->toBeTrue()
        ->and(StockMovementType::Reservation->affectsOnHand())->toBeFalse()
        ->and(StockMovementType::Release->affectsReserved())->toBeTrue()
        ->and(StockMovementType::Adjustment->affectsOnHand())->toBeTrue()
        ->and(StockMovementType::Shipment->affectsOnHand())->toBeTrue()
        ->and(StockMovementType::Shipment->affectsReserved())->toBeTrue();
});

it('knows when a reservation is active', function (): void {
    expect(ReservationStatus::Active->isActive())->toBeTrue()
        ->and(ReservationStatus::Released->isActive())->toBeFalse()
        ->and(ReservationStatus::Consumed->isActive())->toBeFalse();
});

it('computes available-to-promise on a stock item', function (): void {
    $item = new StockItem(['on_hand' => 10, 'reserved' => 4]);

    expect($item->availableToPromise())->toBe(6);
});

it('honours a per-item backorder override over the global policy', function (): void {
    $allowed = new StockItem(['allow_backorder' => true]);
    $denied = new StockItem(['allow_backorder' => false]);
    $inherit = new StockItem(['allow_backorder' => null]);

    expect($allowed->allowsBackorder(BackorderPolicy::Deny))->toBeTrue()
        ->and($denied->allowsBackorder(BackorderPolicy::Allow))->toBeFalse()
        ->and($inherit->allowsBackorder(BackorderPolicy::Allow))->toBeTrue()
        ->and($inherit->allowsBackorder(BackorderPolicy::Deny))->toBeFalse();
});

it('resolves the backorder policy from config, defaulting to deny', function (): void {
    config()->set('commerce.inventory.backorder', 'allow');
    expect(BackorderPolicy::fromConfig())->toBe(BackorderPolicy::Allow);

    config()->set('commerce.inventory.backorder', 'nonsense');
    expect(BackorderPolicy::fromConfig())->toBe(BackorderPolicy::Deny);
});

it('detects an expired reservation by its moment', function (): void {
    $reservation = new StockReservation(['expires_at' => Carbon::parse('2026-01-01 10:00:00')]);

    expect($reservation->isExpiredAt(Carbon::parse('2026-01-01 11:00:00')))->toBeTrue()
        ->and($reservation->isExpiredAt(Carbon::parse('2026-01-01 09:00:00')))->toBeFalse();

    $never = new StockReservation(['expires_at' => null]);
    expect($never->isExpiredAt(Carbon::parse('2999-01-01')))->toBeFalse();
});

it('the null inventory tracks nothing and moves no stock', function (): void {
    $null = new NullInventory;

    // hold/release are no-ops that must not throw.
    $null->hold('cart-1', 'product', 'p1', 1, null);
    $null->release('commerce.cart', 'cart-1', null);

    expect($null->availableToPromise('product', 'p1', null))->toBeNull()
        ->and($null->fulfillOrder('order-1', [['type' => 'product', 'id' => 'p1', 'quantity' => 1, 'name' => 'P']], null))->toBe([]);
});
