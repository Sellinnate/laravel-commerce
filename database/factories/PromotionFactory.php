<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Enums\StackingPolicy;
use Selli\Commerce\Pricing\Models\Promotion;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'priority' => 0,
            'stacking' => StackingPolicy::Cumulative,
            'conditions' => [],
            'actions' => [],
            'active' => true,
        ];
    }
}
