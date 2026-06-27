<?php

declare(strict_types=1);

namespace Selli\Commerce\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\WarehouseFactory;

/**
 * A physical or logical place stock lives. Single-warehouse apps use one default
 * warehouse and never think about it.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $code
 * @property string $name
 * @property int $priority
 * @property bool $active
 */
class Warehouse extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'warehouses';

    protected $guarded = [];

    protected $attributes = [
        'priority' => 0,
        'active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }
}
