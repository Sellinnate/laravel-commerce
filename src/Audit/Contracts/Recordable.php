<?php

declare(strict_types=1);

namespace Selli\Commerce\Audit\Contracts;

use Selli\Commerce\Audit\AuditRecord;

/**
 * Marks a domain event as eligible for the immutable audit trail and tells the
 * recorder exactly how to normalise it.
 */
interface Recordable
{
    public function toAuditRecord(): AuditRecord;
}
