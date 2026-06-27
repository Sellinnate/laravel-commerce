<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Tax\Models\TaxRate;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => 'standard',
            'country' => 'IT',
            'region' => null,
            'name' => 'VAT 22%',
            'rate' => 2200,
            'priority' => 0,
            'active' => true,
        ];
    }
}
