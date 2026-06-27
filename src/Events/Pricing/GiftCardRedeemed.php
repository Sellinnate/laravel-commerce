<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Pricing;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Pricing\Models\GiftCard;

final class GiftCardRedeemed implements Recordable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly GiftCard $giftCard,
        public readonly int $amountMinor,
        public readonly ?string $orderId = null,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: 'GiftCardRedeemed',
            subjectType: $this->giftCard->getMorphClass(),
            subjectId: $this->giftCard->id,
            payload: [
                'amount' => $this->amountMinor,
                'currency' => $this->giftCard->currency,
                'order_id' => $this->orderId,
            ],
            tenantId: $this->giftCard->tenant_id,
        );
    }
}
