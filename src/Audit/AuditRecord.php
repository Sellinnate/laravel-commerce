<?php

declare(strict_types=1);

namespace Selli\Commerce\Audit;

/**
 * A normalised, persistable description of a domain event for the audit trail.
 */
final class AuditRecord
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $subjectType = null,
        public readonly ?string $subjectId = null,
        public readonly array $payload = [],
        public readonly ?string $actorType = null,
        public readonly ?string $actorId = null,
        public readonly ?string $tenantId = null,
    ) {}
}
