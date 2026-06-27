<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Models;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\CouponFactory;
use Selli\Commerce\Enums\CouponType;
use Selli\Commerce\Pricing\CouponValidator;

/**
 * A redeemable discount code with validity, usage limits and an optional
 * minimum spend. Validation is centralised in
 * {@see CouponValidator}.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property string|null $currency
 * @property int|null $min_amount
 * @property string|null $min_amount_currency
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property int $usage_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $active
 */
class Coupon extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'coupons';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'min_amount' => 'integer',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'usage_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    protected $attributes = [
        'usage_count' => 0,
        'active' => true,
    ];

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function minimumAmount(): ?Money
    {
        if ($this->min_amount === null || $this->min_amount_currency === null) {
            return null;
        }

        return Money::ofMinor($this->min_amount, $this->min_amount_currency);
    }

    /**
     * The (positive) discount this coupon yields against a base amount.
     */
    public function discountFor(Money $base): Money
    {
        if ($this->type === CouponType::Percentage) {
            $discount = $base->multipliedBy($this->value)->dividedBy(100, RoundingMode::HalfUp);

            return $discount->isGreaterThan($base) ? $base : $discount;
        }

        $fixed = Money::ofMinor($this->value, $this->currency ?? $base->getCurrency()->getCurrencyCode());

        return $fixed->isGreaterThan($base) ? $base : $fixed;
    }

    public function hasReachedGlobalLimit(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }
}
