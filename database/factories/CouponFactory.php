<?php

declare(strict_types=1);

namespace Selli\Commerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Selli\Commerce\Enums\CouponType;
use Selli\Commerce\Pricing\Models\Coupon;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'type' => CouponType::Percentage,
            'value' => 10,
            'active' => true,
        ];
    }

    public function fixed(int $minorAmount, string $currency = 'EUR'): static
    {
        return $this->state(fn (): array => [
            'type' => CouponType::Fixed,
            'value' => $minorAmount,
            'currency' => $currency,
        ]);
    }
}
