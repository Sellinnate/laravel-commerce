<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Concerns\HasPrefixedTable;

/**
 * Internal per-tenant order-number counter. Not tenant-scoped: it is keyed by a
 * never-null `tenant_key` and incremented under a row lock to guarantee unique,
 * gap-free sequential numbers even under concurrent checkouts.
 *
 * @property string $tenant_key
 * @property string|null $tenant_id
 * @property int $next_number
 */
class OrderSequence extends Model
{
    use HasPrefixedTable;

    protected string $baseTable = 'order_sequences';

    protected $primaryKey = 'tenant_key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
