<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Pricing;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Order\Models\Order;

final class PromotionApplied implements Recordable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly string $promotionId,
        public readonly string $name,
        public readonly int $amountMinor,
        public readonly string $currency,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: 'PromotionApplied',
            subjectType: $this->order->getMorphClass(),
            subjectId: $this->order->id,
            payload: [
                'promotion_id' => $this->promotionId,
                'name' => $this->name,
                'amount' => $this->amountMinor,
                'currency' => $this->currency,
            ],
            tenantId: $this->order->tenant_id,
        );
    }
}
