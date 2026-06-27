<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\Models\OrderLine;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = Money::ofMinor($this->faker->numberBetween(100, 10000), 'EUR');
        $quantity = $this->faker->numberBetween(1, 5);
        $subtotal = $unit->multipliedBy($quantity);

        return [
            'order_id' => Order::factory(),
            'purchasable_type' => 'product',
            'purchasable_id' => (string) Str::ulid(),
            'name' => $this->faker->words(2, true),
            'sku' => Str::upper(Str::random(6)),
            'quantity' => $quantity,
            'unit_price' => $unit,
            'line_subtotal' => $subtotal,
            'discount_total' => Money::ofMinor(0, 'EUR'),
            'tax_total' => Money::ofMinor(0, 'EUR'),
            'line_total' => $subtotal,
            'snapshot' => [],
            'tax_detail' => [],
            'discount_detail' => [],
            'metadata' => [],
        ];
    }
}
