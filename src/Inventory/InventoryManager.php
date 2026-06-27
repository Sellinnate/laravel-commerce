<?php

declare(strict_types=1);

namespace Selli\Commerce\Inventory;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Selli\Commerce\Contracts\StockKeeper;
use Selli\Commerce\Contracts\StockResolver;
use Selli\Commerce\Enums\BackorderPolicy;
use Selli\Commerce\Enums\ReservationStatus;
use Selli\Commerce\Enums\StockMovementType;
use Selli\Commerce\Events\Inventory\BackorderCreated;
use Selli\Commerce\Events\Inventory\StockDepleted;
use Selli\Commerce\Events\Inventory\StockReleased;
use Selli\Commerce\Events\Inventory\StockReserved;
use Selli\Commerce\Exceptions\InsufficientStockException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Inventory\Models\StockItem;
use Selli\Commerce\Inventory\Models\StockMovement;
use Selli\Commerce\Inventory\Models\StockReservation;
use Selli\Commerce\Inventory\Models\Warehouse;

/**
 * The Inventory module's engine. Tracks stock per purchasable × warehouse
 * through an append-only ledger, answers available-to-promise (on_hand −
 * reserved) and fulfils orders under a row lock so two buyers can never both win
 * the last unit. Every query is scoped explicitly to the row's tenant (never the
 * ambient scope, which is null during system/guest placement).
 */
final class InventoryManager implements StockKeeper, StockResolver
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function availableToPromise(string $type, string $id, ?string $tenantId): ?int
    {
        if (! $this->stockItemQuery($type, $id, $tenantId)->exists()) {
            // No stock row anywhere → the purchasable is not stock-tracked.
            return null;
        }

        // Only count on-hand in ACTIVE warehouses: fulfilment ships only from
        // active ones, so promising stock in a disabled warehouse would lead to
        // a shortfall at checkout. A tracked SKU that lives only in inactive
        // warehouses therefore reports ATP ≤ 0 rather than null.
        $onHand = $this->stockItemQuery($type, $id, $tenantId)
            ->join($this->warehouseTable(), $this->stockTable().'.warehouse_id', '=', $this->warehouseTable().'.id')
            ->where($this->warehouseTable().'.active', true)
            ->sum($this->stockTable().'.on_hand');

        return (int) $onHand - $this->holdingReserved($type, $id, $tenantId, $this->now());
    }

    /**
     * Bring stock into a warehouse (a goods receipt). Returns the updated item.
     */
    public function receive(string $type, string $id, int $quantity, ?string $tenantId = null, ?string $warehouseCode = null, ?string $reason = null): StockItem
    {
        return DB::transaction(function () use ($type, $id, $quantity, $tenantId, $warehouseCode, $reason): StockItem {
            $warehouse = $this->warehouse($tenantId, $warehouseCode);
            $item = $this->lockItem($type, $id, $tenantId, $warehouse->id);

            $item->on_hand += $quantity;
            $item->version++;
            $item->save();

            $this->record($warehouse->id, $type, $id, StockMovementType::Receipt, $quantity, $tenantId, $reason);

            return $item;
        });
    }

    /**
     * Rectify the counted quantity by a signed delta (a stock-take correction).
     */
    public function adjust(string $type, string $id, int $delta, ?string $tenantId = null, ?string $warehouseCode = null, ?string $reason = null): StockItem
    {
        return DB::transaction(function () use ($type, $id, $delta, $tenantId, $warehouseCode, $reason): StockItem {
            $warehouse = $this->warehouse($tenantId, $warehouseCode);
            $item = $this->lockItem($type, $id, $tenantId, $warehouse->id);

            $item->on_hand += $delta;
            $item->version++;
            $item->save();

            $this->record($warehouse->id, $type, $id, StockMovementType::Adjustment, $delta, $tenantId, $reason);

            return $item;
        });
    }

    public function hold(string $cartId, string $type, string $id, int $quantity, ?string $tenantId): void
    {
        DB::transaction(function () use ($cartId, $type, $id, $quantity, $tenantId): void {
            // Clean expired holds first so the counts are accurate, then take the
            // row lock — concurrent holds serialise here.
            $this->releaseExpiredFor($type, $id, $tenantId);

            $warehouse = $this->warehouse($tenantId, null);
            $item = $this->lockItem($type, $id, $tenantId, $warehouse->id);

            $existing = StockReservation::withoutTenantScope()
                ->where('reference_type', 'commerce.cart')
                ->where('reference_id', $cartId)
                ->where('purchasable_type', $type)
                ->where('purchasable_id', $id)
                ->where('status', ReservationStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($quantity <= 0) {
                // The line is gone (removed, or merged away): give the hold back.
                if ($existing !== null) {
                    $this->releaseReservation($existing);
                }

                return;
            }

            $previous = $existing !== null ? $existing->quantity : 0;
            $delta = $quantity - $previous;

            if ($delta === 0 && $existing !== null) {
                // Quantity unchanged: just refresh the TTL so the hold survives.
                $existing->expires_at = $this->expiry();
                $existing->save();

                return;
            }

            // Atomically re-validate availability for a positive delta under the
            // lock: the cart's pre-lock check is advisory, so two concurrent
            // add-to-cart holds must be reconciled here. ATP already accounts for
            // this cart's previous hold, so the new units we may take is exactly
            // the current ATP.
            if ($delta > 0 && ! $this->allowsBackorder($type, $id, $tenantId)) {
                $available = $this->availableToPromise($type, $id, $tenantId);

                if ($available !== null && $delta > $available) {
                    // Surface the same exception the cart's own availability check
                    // raises, so a concurrent add/merge fails consistently rather
                    // than leaking the fulfilment-time InsufficientStockException.
                    throw ProductNotAvailableException::for("purchasable {$id}", $quantity);
                }
            }

            if ($existing !== null) {
                $existing->quantity = $quantity;
                $existing->expires_at = $this->expiry();
                $existing->save();
            } else {
                StockReservation::create([
                    'tenant_id' => $tenantId,
                    'warehouse_id' => $warehouse->id,
                    'purchasable_type' => $type,
                    'purchasable_id' => $id,
                    'quantity' => $quantity,
                    'status' => ReservationStatus::Active,
                    'reference_type' => 'commerce.cart',
                    'reference_id' => $cartId,
                    'expires_at' => $this->expiry(),
                ]);
            }

            $item->reserved += $delta;
            $item->version++;
            $item->save();

            $this->record($warehouse->id, $type, $id, StockMovementType::Reservation, $delta, $tenantId, 'cart hold', 'commerce.cart', $cartId);

            if ($delta > 0) {
                $this->events->dispatch(new StockReserved($type, $id, $delta, $warehouse->id, $tenantId, 'commerce.cart', $cartId));
            }
        });
    }

    public function release(string $referenceType, string $referenceId, ?string $tenantId): void
    {
        DB::transaction(function () use ($referenceType, $referenceId): void {
            $reservations = StockReservation::withoutTenantScope()
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('status', ReservationStatus::Active->value)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $this->releaseReservation($reservation);
            }
        });
    }

    public function fulfillOrder(string $orderId, array $lines, ?string $tenantId, ?string $cartId = null): array
    {
        return DB::transaction(function () use ($orderId, $lines, $tenantId, $cartId): array {
            $backordered = [];

            foreach ($this->aggregate($lines) as $line) {
                $type = $line['type'];
                $id = $line['id'];
                $needed = $line['quantity'];
                $name = $line['name'];

                if (! $this->stockItemQuery($type, $id, $tenantId)->exists()) {
                    // Not stock-tracked: nothing to decrement, host owns availability.
                    continue;
                }

                // Release any expired holds for this purchasable first, so the
                // reserved counters reflect only live claims: an expired hold
                // must never get a free pass to ship (which could oversell) and
                // must not make on-hand stock look unavailable.
                $this->releaseExpiredFor($type, $id, $tenantId);

                // Consume the originating cart's still-live holds, so held stock
                // is shipped rather than double-counted. (Its expired holds were
                // just released above and fall through to a fresh, locked ship.)
                if ($cartId !== null) {
                    $needed = $this->consumeCartHolds($cartId, $orderId, $type, $id, $needed, $tenantId);
                }

                // Then ship the remainder from on-hand stock, warehouse by
                // warehouse (cheapest priority first), each under a row lock.
                $needed = $this->shipFromStock($orderId, $type, $id, $needed, $tenantId);

                if ($needed > 0) {
                    $available = $this->availableToPromise($type, $id, $tenantId) ?? 0;

                    if (! $this->allowsBackorder($type, $id, $tenantId)) {
                        throw InsufficientStockException::for($name, $line['quantity'], max(0, $available));
                    }

                    $this->shipBackorder($orderId, $type, $id, $needed, $tenantId);
                    $backordered[] = ['type' => $type, 'id' => $id, 'quantity' => $needed];
                }
            }

            return $backordered;
        });
    }

    /**
     * Release every reservation that has passed its TTL. Returns the count
     * released. Used by the scheduled release-expired command.
     */
    public function releaseExpired(?Carbon $moment = null): int
    {
        $moment ??= $this->now();

        // One transaction holds the locks for the whole sweep, so overlapping
        // sweeps serialise and a reservation is never released twice.
        return DB::transaction(function () use ($moment): int {
            $expired = StockReservation::withoutTenantScope()
                ->where('status', ReservationStatus::Active->value)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $moment)
                ->lockForUpdate()
                ->get();

            foreach ($expired as $reservation) {
                $this->releaseReservation($reservation);
            }

            return $expired->count();
        });
    }

    // ---------------------------------------------------------------------

    /**
     * Release every expired-but-still-active hold for a purchasable, inside the
     * current transaction, so the reserved counters reflect only live claims.
     */
    private function releaseExpiredFor(string $type, string $id, ?string $tenantId): void
    {
        StockReservation::withoutTenantScope()
            ->where('purchasable_type', $type)
            ->where('purchasable_id', $id)
            ->when($tenantId === null, fn (Builder $q) => $q->whereNull('tenant_id'), fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->where('status', ReservationStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $this->now())
            ->lockForUpdate()
            ->get()
            ->each(fn (StockReservation $reservation) => $this->releaseReservation($reservation));
    }

    /**
     * Consume the cart's active holds for a purchasable, turning held stock into
     * shipments attributed to the order. Returns the still-unfulfilled quantity.
     */
    private function consumeCartHolds(string $cartId, string $orderId, string $type, string $id, int $needed, ?string $tenantId): int
    {
        $holds = StockReservation::withoutTenantScope()
            ->where('reference_type', 'commerce.cart')
            ->where('reference_id', $cartId)
            ->where('purchasable_type', $type)
            ->where('purchasable_id', $id)
            ->where('status', ReservationStatus::Active->value)
            ->lockForUpdate()
            ->get();

        foreach ($holds as $hold) {
            if ($needed <= 0) {
                // The order no longer needs this hold — give it back.
                $this->releaseReservation($hold);

                continue;
            }

            $take = min($hold->quantity, $needed);
            $item = $this->lockItem($type, $id, $tenantId, $hold->warehouse_id);

            // Cap the shipment at what is physically on hand: an admin may have
            // adjusted stock down below the held amount, and a hold must never be
            // a free pass to drive on_hand negative under a deny policy. Whatever
            // the hold cannot cover falls through to shipFromStock / the backorder
            // policy like any other shortfall.
            $ship = max(0, min($take, $item->on_hand));
            // The whole reservation is closed here, so the FULL held quantity
            // leaves `reserved`: `ship` units leave on_hand too, and the rest is
            // released — never orphaned.
            $released = $hold->quantity - $ship;

            $item->on_hand -= $ship;
            $item->reserved -= $hold->quantity;
            $item->version++;
            $item->save();

            if ($ship > 0) {
                $this->record($hold->warehouse_id, $type, $id, StockMovementType::Shipment, -$ship, $tenantId, 'order fulfilment', 'commerce.order', $orderId);
            }

            if ($released > 0) {
                $this->record($hold->warehouse_id, $type, $id, StockMovementType::Release, -$released, $tenantId, 'cart hold settled', 'commerce.order', $orderId);
            }

            $hold->status = ReservationStatus::Consumed;
            $hold->reference_type = 'commerce.order';
            $hold->reference_id = $orderId;
            $hold->save();

            $this->depletedIfEmpty($item, $type, $id, $tenantId);

            // Only what actually shipped reduces the order's need; any shortfall
            // the hold could not cover flows to shipFromStock / the backorder gate.
            $needed -= $ship;
        }

        return $needed;
    }

    /**
     * Ship the needed quantity from on-hand stock across warehouses (priority
     * order) under a lock. Returns the quantity still unfulfilled (a shortfall).
     */
    private function shipFromStock(string $orderId, string $type, string $id, int $needed, ?string $tenantId): int
    {
        if ($needed <= 0) {
            return 0;
        }

        // Order the purchasable's stock items by their warehouse priority, then
        // lock and ship from each in turn. Selecting the stock columns keeps the
        // rows typed as StockItem models (warehouse_id is a string).
        $items = $this->stockItemQuery($type, $id, $tenantId)
            ->join($this->warehouseTable(), $this->stockTable().'.warehouse_id', '=', $this->warehouseTable().'.id')
            ->where($this->warehouseTable().'.active', true)
            ->orderBy($this->warehouseTable().'.priority')
            ->orderBy($this->warehouseTable().'.id')
            ->select($this->stockTable().'.*')
            ->get();

        foreach ($items as $unlocked) {
            if ($needed <= 0) {
                break;
            }

            $warehouseId = $unlocked->warehouse_id;
            $item = $this->lockItem($type, $id, $tenantId, $warehouseId);
            $available = $item->on_hand - $item->reserved;

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $needed);
            $item->on_hand -= $take;
            $item->version++;
            $item->save();

            $this->record($warehouseId, $type, $id, StockMovementType::Shipment, -$take, $tenantId, 'order fulfilment', 'commerce.order', $orderId);
            $this->depletedIfEmpty($item, $type, $id, $tenantId);

            $needed -= $take;
        }

        return $needed;
    }

    private function shipBackorder(string $orderId, string $type, string $id, int $quantity, ?string $tenantId): void
    {
        $warehouse = $this->warehouse($tenantId, null);
        $item = $this->lockItem($type, $id, $tenantId, $warehouse->id);

        $item->on_hand -= $quantity;
        $item->version++;
        $item->save();

        $this->record($warehouse->id, $type, $id, StockMovementType::Shipment, -$quantity, $tenantId, 'backorder', 'commerce.order', $orderId);

        $this->events->dispatch(new BackorderCreated($type, $id, $quantity, $warehouse->id, $tenantId, 'commerce.order', $orderId));
        $this->depletedIfEmpty($item, $type, $id, $tenantId);
    }

    private function releaseReservation(StockReservation $reservation): void
    {
        // Idempotency guard: only an active reservation holds stock, so a row
        // already released/consumed must not be processed (and double-counted)
        // again by an overlapping sweep.
        if ($reservation->status !== ReservationStatus::Active) {
            return;
        }

        $item = $this->lockItem(
            $reservation->purchasable_type,
            $reservation->purchasable_id,
            $reservation->tenant_id,
            $reservation->warehouse_id,
        );

        $item->reserved = max(0, $item->reserved - $reservation->quantity);
        $item->version++;
        $item->save();

        $this->record(
            $reservation->warehouse_id,
            $reservation->purchasable_type,
            $reservation->purchasable_id,
            StockMovementType::Release,
            -$reservation->quantity,
            $reservation->tenant_id,
            'reservation released',
            $reservation->reference_type,
            $reservation->reference_id,
        );

        $reservation->status = ReservationStatus::Released;
        $reservation->save();

        $this->events->dispatch(new StockReleased(
            $reservation->purchasable_type,
            $reservation->purchasable_id,
            $reservation->quantity,
            $reservation->warehouse_id,
            $reservation->tenant_id,
            $reservation->reference_type,
            $reservation->reference_id,
        ));
    }

    private function depletedIfEmpty(StockItem $item, string $type, string $id, ?string $tenantId): void
    {
        if ($item->on_hand <= 0) {
            $this->events->dispatch(new StockDepleted($type, $id, $item->on_hand, $item->warehouse_id, $tenantId));
        }
    }

    /**
     * Lock (or create) the stock item row for a purchasable in a warehouse.
     */
    private function lockItem(string $type, string $id, ?string $tenantId, string $warehouseId): StockItem
    {
        $item = StockItem::withoutTenantScope()
            ->where('warehouse_id', $warehouseId)
            ->where('purchasable_type', $type)
            ->where('purchasable_id', $id)
            ->when($tenantId === null, fn (Builder $q) => $q->whereNull('tenant_id'), fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($item instanceof StockItem) {
            return $item;
        }

        return StockItem::create([
            'tenant_id' => $tenantId,
            'warehouse_id' => $warehouseId,
            'purchasable_type' => $type,
            'purchasable_id' => $id,
            'on_hand' => 0,
            'reserved' => 0,
        ]);
    }

    /**
     * Resolve (or create) a warehouse by code, defaulting to the configured one.
     */
    private function warehouse(?string $tenantId, ?string $code): Warehouse
    {
        $code ??= Config::string('commerce.inventory.default_warehouse', 'default');

        $warehouse = Warehouse::withoutTenantScope()
            ->where('code', $code)
            ->when($tenantId === null, fn (Builder $q) => $q->whereNull('tenant_id'), fn (Builder $q) => $q->where('tenant_id', $tenantId))
            // Deterministic pick: a null-tenant unique index does not stop two
            // concurrent first-time creates from inserting a duplicate default,
            // so always resolve the oldest row by its monotonic ULID — every
            // automatic operation then targets the same warehouse.
            ->orderBy('id')
            ->first();

        if ($warehouse instanceof Warehouse) {
            return $warehouse;
        }

        return Warehouse::create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => ucfirst($code).' Warehouse',
        ]);
    }

    public function allowsBackorder(string $type, string $id, ?string $tenantId): bool
    {
        $default = BackorderPolicy::fromConfig();

        $item = $this->stockItemQuery($type, $id, $tenantId)
            ->whereNotNull('allow_backorder')
            ->orderByDesc('allow_backorder')
            ->first();

        return $item instanceof StockItem ? $item->allowsBackorder($default) : $default->allowsBackorder();
    }

    public function heldQuantity(string $referenceType, string $referenceId, string $type, string $id, ?string $tenantId): int
    {
        return (int) StockReservation::withoutTenantScope()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('purchasable_type', $type)
            ->where('purchasable_id', $id)
            ->when($tenantId === null, fn (Builder $q) => $q->whereNull('tenant_id'), fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->holding($this->now())
            ->sum('quantity');
    }

    private function holdingReserved(string $type, string $id, ?string $tenantId, Carbon $moment): int
    {
        return (int) StockReservation::withoutTenantScope()
            ->where('purchasable_type', $type)
            ->where('purchasable_id', $id)
            ->when($tenantId === null, fn (Builder $q) => $q->whereNull('tenant_id'), fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->holding($moment)
            ->sum('quantity');
    }

    /**
     * @return Builder<StockItem>
     */
    private function stockItemQuery(string $type, string $id, ?string $tenantId): Builder
    {
        // Columns are table-qualified so the query stays unambiguous when joined
        // to the warehouses table during fulfilment.
        $table = $this->stockTable();

        return StockItem::withoutTenantScope()
            ->where($table.'.purchasable_type', $type)
            ->where($table.'.purchasable_id', $id)
            ->when(
                $tenantId === null,
                fn (Builder $q) => $q->whereNull($table.'.tenant_id'),
                fn (Builder $q) => $q->where($table.'.tenant_id', $tenantId),
            );
    }

    private function record(string $warehouseId, string $type, string $id, StockMovementType $movementType, int $quantity, ?string $tenantId, ?string $reason = null, ?string $referenceType = null, ?string $referenceId = null): void
    {
        StockMovement::create([
            'tenant_id' => $tenantId,
            'warehouse_id' => $warehouseId,
            'purchasable_type' => $type,
            'purchasable_id' => $id,
            'type' => $movementType,
            'quantity' => $quantity,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * @param  list<array{type: string, id: string, quantity: int, name: string}>  $lines
     * @return list<array{type: string, id: string, quantity: int, name: string}>
     */
    private function aggregate(array $lines): array
    {
        $byKey = [];

        foreach ($lines as $line) {
            $key = $line['type'].'|'.$line['id'];

            if (! isset($byKey[$key])) {
                $byKey[$key] = ['type' => $line['type'], 'id' => $line['id'], 'quantity' => 0, 'name' => $line['name']];
            }

            $byKey[$key]['quantity'] += $line['quantity'];
        }

        return array_values($byKey);
    }

    private function expiry(): ?Carbon
    {
        $minutes = Config::get('commerce.inventory.reservation_ttl');

        if (! is_numeric($minutes)) {
            return null;
        }

        return $this->now()->addMinutes((int) $minutes);
    }

    private function now(): Carbon
    {
        return Carbon::now();
    }

    private function stockTable(): string
    {
        return (new StockItem)->getTable();
    }

    private function warehouseTable(): string
    {
        return (new Warehouse)->getTable();
    }
}
