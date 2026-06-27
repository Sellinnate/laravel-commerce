<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Cart\Models\CartItem;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'purchasable_type' => 'product',
            'purchasable_id' => (string) Str::ulid(),
            'name' => $this->faker->words(2, true),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => Money::ofMinor($this->faker->numberBetween(100, 10000), 'EUR'),
            'options' => [],
            'metadata' => [],
        ];
    }
}
