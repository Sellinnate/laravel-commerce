<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Order;

use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Order\Models\Order;

final class OrderStateTransitioned extends OrderEvent
{
    public function __construct(
        Order $order,
        public readonly ?string $from,
        public readonly string $to,
        public readonly ?string $actorType = null,
        public readonly ?string $actorId = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($order);
    }

    protected function payload(): array
    {
        return [
            'number' => $this->order->number,
            'from' => $this->from,
            'to' => $this->to,
            'reason' => $this->reason,
        ];
    }

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: $this->eventName(),
            subjectType: $this->order->getMorphClass(),
            subjectId: $this->order->id,
            payload: $this->payload(),
            actorType: $this->actorType,
            actorId: $this->actorId,
            tenantId: $this->order->tenant_id,
        );
    }
}
