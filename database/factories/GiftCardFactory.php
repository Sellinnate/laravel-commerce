<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Selli\Commerce\Pricing\Models\GiftCard;

/**
 * @extends Factory<GiftCard>
 */
class GiftCardFactory extends Factory
{
    protected $model = GiftCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000, 10000);

        return [
            'code' => Str::upper(Str::random(10)),
            'initial_amount' => $amount,
            'balance' => $amount,
            'currency' => 'EUR',
            'active' => true,
        ];
    }
}
