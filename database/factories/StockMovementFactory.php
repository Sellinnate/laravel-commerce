<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Enums\StockMovementType;
use Selli\Commerce\Inventory\Models\StockMovement;
use Selli\Commerce\Inventory\Models\Warehouse;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'purchasable_type' => 'product',
            'purchasable_id' => (string) fake()->numberBetween(1, 1_000_000),
            'type' => StockMovementType::Receipt,
            'quantity' => 100,
            'reason' => null,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }
}
