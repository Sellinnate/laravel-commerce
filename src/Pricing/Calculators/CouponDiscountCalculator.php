<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Calculators;

use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\CouponValidator;
use Selli\Commerce\Contracts\RoundingStrategy;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Exceptions\CommerceException;
use Selli\Commerce\Pricing\Models\Coupon;

/**
 * Applies the cart's stored coupon codes as discount adjustments. Acceptance is
 * delegated to the {@see CouponValidator} contract (so a custom binding governs
 * the pipeline too); the discount amount comes from the Coupon record. Coupons
 * apply to the subtotal net of promotions, each on the running balance, and any
 * code that is no longer valid is silently skipped (calculation never throws).
 */
final class CouponDiscountCalculator implements Calculator
{
    public function __construct(
        private readonly CouponValidator $coupons,
        private readonly RoundingStrategy $rounding,
    ) {}

    public function apply(Calculation $calculation): void
    {
        $codes = $this->codes($calculation);

        if ($codes === []) {
            return;
        }

        $context = [
            'currency' => $calculation->currency,
            'customer' => $calculation->context['customer'] ?? null,
            'tenant_id' => $calculation->context['tenant_id'] ?? null,
        ];

        // Minimum-spend is validated against the promotion-net subtotal (the
        // same base CartManager::applyCoupon checks), while the discount itself
        // is taken from the running balance after earlier coupons.
        $promotionNetSubtotal = $calculation->itemsSubtotal()->plus($calculation->totalByType(AdjustmentType::Promotion));
        $base = $promotionNetSubtotal;

        foreach ($codes as $code) {
            if ($base->isNegativeOrZero()) {
                break;
            }

            try {
                $this->coupons->validate($code, $context + ['subtotal' => $promotionNetSubtotal]);
            } catch (CommerceException) {
                continue;
            }

            $coupon = Coupon::query()->where('code', $code)->first();

            if (! $coupon instanceof Coupon) {
                continue;
            }

            $discount = $this->rounding->round($coupon->discountFor($base));

            if ($discount->isZero()) {
                continue;
            }

            $calculation->addAdjustment(new Adjustment(
                AdjustmentType::Discount,
                "Coupon {$code}",
                $discount->multipliedBy(-1),
                'coupon',
                true,
                ['code' => $code, 'coupon_id' => $coupon->id],
            ));

            $base = $base->minus($discount);
        }
    }

    public function identifier(): string
    {
        return 'coupon_discount';
    }

    /**
     * @return list<string>
     */
    private function codes(Calculation $calculation): array
    {
        $metadata = $calculation->context['metadata'] ?? [];

        if (! is_array($metadata)) {
            return [];
        }

        $codes = $metadata['coupons'] ?? [];

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_filter($codes, 'is_string'));
    }
}
