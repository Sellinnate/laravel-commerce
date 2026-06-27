<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Enums\ReservationStatus;
use Selli\Commerce\Inventory\Models\StockReservation;
use Selli\Commerce\Inventory\Models\Warehouse;

/**
 * @extends Factory<StockReservation>
 */
class StockReservationFactory extends Factory
{
    protected $model = StockReservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'purchasable_type' => 'product',
            'purchasable_id' => (string) fake()->numberBetween(1, 1_000_000),
            'quantity' => 1,
            'status' => ReservationStatus::Active,
            'reference_type' => null,
            'reference_id' => null,
            'expires_at' => null,
        ];
    }
}
