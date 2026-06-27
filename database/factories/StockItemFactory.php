<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Inventory\Models\StockItem;
use Selli\Commerce\Inventory\Models\Warehouse;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    protected $model = StockItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'purchasable_type' => 'product',
            'purchasable_id' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'on_hand' => 100,
            'reserved' => 0,
            'allow_backorder' => null,
            'version' => 0,
        ];
    }
}
