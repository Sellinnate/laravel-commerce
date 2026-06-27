<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Inventory\Models\Warehouse;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'default',
            'name' => 'Default Warehouse',
            'priority' => 0,
            'active' => true,
        ];
    }
}
