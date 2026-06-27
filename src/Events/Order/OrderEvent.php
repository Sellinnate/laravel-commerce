<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Order;

use Illuminate\Foundation\Events\Dispatchable;
use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Order\Models\Order;

abstract class OrderEvent implements Recordable
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}

    protected function eventName(): string
    {
        return class_basename(static::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'number' => $this->order->number,
            'state' => $this->order->state::$name,
            'grand_total' => $this->order->grand_total?->getMinorAmount()->toInt(),
            'currency' => $this->order->currency,
        ];
    }

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: $this->eventName(),
            subjectType: $this->order->getMorphClass(),
            subjectId: $this->order->id,
            payload: $this->payload(),
            tenantId: $this->order->tenant_id,
        );
    }
}
