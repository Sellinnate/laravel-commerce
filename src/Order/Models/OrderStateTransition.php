<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\HasPrefixedTable;

/**
 * Append-only log of every order state change — the heart of the audit trail.
 * Rows are never updated or deleted.
 *
 * @property string $id
 * @property string $order_id
 * @property string|null $tenant_id
 * @property string|null $from_state
 * @property string $to_state
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $reason
 * @property array<string, mixed> $metadata
 * @property Carbon $created_at
 */
class OrderStateTransition extends Model
{
    use HasPrefixedTable;
    use HasUlids;

    public const UPDATED_AT = null;

    protected string $baseTable = 'order_state_transitions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'metadata' => '{}',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
