<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\States\Pending;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'ORD-'.Str::upper(Str::random(8)),
            'currency' => 'EUR',
            'state' => Pending::class,
            'subtotal' => Money::ofMinor(0, 'EUR'),
            'discount_total' => Money::ofMinor(0, 'EUR'),
            'tax_total' => Money::ofMinor(0, 'EUR'),
            'shipping_total' => Money::ofMinor(0, 'EUR'),
            'grand_total' => Money::ofMinor(0, 'EUR'),
            'metadata' => [],
            'placed_at' => now(),
        ];
    }
}
