<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Selli\Commerce\Pricing\Models\Price;
use Selli\Commerce\Pricing\Models\PriceBook;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    protected $model = Price::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_book_id' => PriceBook::factory(),
            'purchasable_type' => 'product',
            'purchasable_id' => (string) Str::ulid(),
            'amount' => $this->faker->numberBetween(100, 10000),
            'currency' => 'EUR',
            'min_quantity' => 1,
        ];
    }
}
