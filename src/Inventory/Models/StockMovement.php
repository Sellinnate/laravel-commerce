<?php

declare(strict_types=1);

namespace Selli\Commerce\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\AppendOnly;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\StockMovementFactory;
use Selli\Commerce\Enums\StockMovementType;

/**
 * One immutable line in the stock ledger. The current position of a stock item
 * is reconstructable as the sum of its movements; the {@see StockItem}
 * projection is just a cached, lock-friendly view of that sum.
 *
 * Append-only: never updated, never deleted — the ledger is the audit trail.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $warehouse_id
 * @property string $purchasable_type
 * @property string $purchasable_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property string|null $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property Carbon|null $created_at
 */
class StockMovement extends Model
{
    use AppendOnly;
    use BelongsToTenant;

    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    public const UPDATED_AT = null;

    protected string $baseTable = 'stock_movements';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }
}
