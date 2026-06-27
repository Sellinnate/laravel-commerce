<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Cart;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Selli\Commerce\Audit\AuditRecord;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Cart\Models\Cart;

/**
 * Base for cart domain events. The core emits and never presumes who listens.
 *
 * Implements {@see ShouldDispatchAfterCommit} so events raised inside a DB
 * transaction are only dispatched once it commits — never before, and never at
 * all if it rolls back (dispatched immediately when no transaction is open).
 */
abstract class CartEvent implements Recordable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Cart $cart,
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
        return [];
    }

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            name: $this->eventName(),
            subjectType: $this->cart->getMorphClass(),
            subjectId: $this->cart->id,
            payload: $this->payload(),
            tenantId: $this->cart->tenant_id,
        );
    }
}
