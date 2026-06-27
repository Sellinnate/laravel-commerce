<?php

declare(strict_types=1);

namespace Selli\Commerce\Audit;

use Illuminate\Support\Facades\Config;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Audit\Models\DomainEvent;

/**
 * Persists every {@see Recordable} domain event append-only — Audit level 1.
 * Wired as a wildcard event listener by the service provider.
 */
final class RecordDomainEvents
{
    public function handle(Recordable $event): void
    {
        if (! Config::boolean('commerce.audit.record_domain_events', true)) {
            return;
        }

        $record = $event->toAuditRecord();

        DomainEvent::query()->create([
            'tenant_id' => $record->tenantId,
            'name' => $record->name,
            'subject_type' => $record->subjectType,
            'subject_id' => $record->subjectId,
            'payload' => $record->payload,
            'actor_type' => $record->actorType,
            'actor_id' => $record->actorId,
        ]);
    }
}
