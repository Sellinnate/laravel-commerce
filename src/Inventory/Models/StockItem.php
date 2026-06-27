<?php

declare(strict_types=1);

namespace Selli\Commerce\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\StockItemFactory;
use Selli\Commerce\Enums\BackorderPolicy;

/**
 * The materialised stock position for a purchasable in one warehouse: on_hand is
 * the counted quantity, reserved is held against active reservations. The
 * available-to-promise is {@see availableToPromise()} = on_hand − reserved, and
 * is the figure the cart consults — never the gross on_hand.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $warehouse_id
 * @property string $purchasable_type
 * @property string $purchasable_id
 * @property int $on_hand
 * @property int $reserved
 * @property bool|null $allow_backorder
 * @property int $version
 */
class StockItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<StockItemFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'stock_items';

    protected $guarded = [];

    protected $attributes = [
        'on_hand' => 0,
        'reserved' => 0,
        'version' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'allow_backorder' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function availableToPromise(): int
    {
        return $this->on_hand - $this->reserved;
    }

    /**
     * Whether this item permits selling below available stock, honouring a
     * per-item override of the global policy when one is set.
     */
    public function allowsBackorder(BackorderPolicy $default): bool
    {
        return $this->allow_backorder ?? $default->allowsBackorder();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected static function newFactory(): StockItemFactory
    {
        return StockItemFactory::new();
    }
}
