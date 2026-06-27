<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Pricing\Models\PriceBook;

/**
 * @extends Factory<PriceBook>
 */
class PriceBookFactory extends Factory
{
    protected $model = PriceBook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'currency' => 'EUR',
            'segment' => null,
            'priority' => 0,
            'active' => true,
        ];
    }
}
