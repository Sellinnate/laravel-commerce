<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Inventory;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Audit\Contracts\Recordable;

/**
 * Base for inventory domain events. Carries the purchasable, the quantity moved,
 * the warehouse and the reference (cart/order) that caused it. Dispatched after
 * commit so listeners (reorder, alerts, ERP sync) never see uncommitted stock.
 */
abstract class StockEvent implements Recordable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $purchasableType,
        public readonly string $purchasableId,
        public readonly int $quantity,
        public readonly string $warehouseId,
        public readonly ?string $tenantId = null,
        public readonly ?string $referenceType = null,
        public readonly ?string $referenceId = null,
    ) {}

    protected function eventName(): string
    {
        return class_basename(static::class);
    }

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: $this->eventName(),
            subjectType: $this->purchasableType,
            subjectId: $this->purchasableId,
            payload: [
                'quantity' => $this->quantity,
                'warehouse_id' => $this->warehouseId,
                'reference_type' => $this->referenceType,
                'reference_id' => $this->referenceId,
            ],
            tenantId: $this->tenantId,
        );
    }
}
