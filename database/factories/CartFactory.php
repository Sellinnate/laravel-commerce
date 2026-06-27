<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Enums\CartStatus;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => null,
            'owner_id' => null,
            'currency' => 'EUR',
            'status' => CartStatus::Active,
            'metadata' => [],
            'expires_at' => now()->addDays(7),
        ];
    }

    public function converted(): static
    {
        return $this->state(fn (): array => ['status' => CartStatus::Converted]);
    }
}
