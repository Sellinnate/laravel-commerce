<?php

declare(strict_types=1);

namespace Selli\Commerce\Audit\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\AppendOnly;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;

/**
 * Append-only persistence of every domain event — Audit level 1, always on.
 * "Who did what, when and why", reconstructable over time.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed> $payload
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property Carbon $created_at
 */
class DomainEvent extends Model
{
    use AppendOnly;
    use BelongsToTenant;
    use HasPrefixedTable;
    use HasUlids;

    public const UPDATED_AT = null;

    protected string $baseTable = 'domain_events';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'payload' => '{}',
    ];
}
