<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Pricing;

use Illuminate\Foundation\Events\Dispatchable;
use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Cart\Models\Cart;

final class CouponRejected implements Recordable
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $cart,
        public readonly string $code,
        public readonly string $reason,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: 'CouponRejected',
            subjectType: $this->cart->getMorphClass(),
            subjectId: $this->cart->id,
            payload: ['code' => $this->code, 'reason' => $this->reason],
            tenantId: $this->cart->tenant_id,
        );
    }
}
