<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Pricing;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Cart\Models\Cart;

final class CouponApplied implements Recordable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $cart,
        public readonly string $code,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: 'CouponApplied',
            subjectType: $this->cart->getMorphClass(),
            subjectId: $this->cart->id,
            payload: ['code' => $this->code],
            tenantId: $this->cart->tenant_id,
        );
    }
}
