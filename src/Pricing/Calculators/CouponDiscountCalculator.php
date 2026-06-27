<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Calculators;

use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\RoundingStrategy;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Exceptions\CommerceException;
use Selli\Commerce\Pricing\DatabaseCouponValidator;

/**
 * Applies the cart's stored coupon codes as discount adjustments. Coupons apply
 * to the subtotal already net of promotions, each on the running balance.
 * Codes that have since become invalid are silently skipped (calculation is
 * read-only and must never throw).
 */
final class CouponDiscountCalculator implements Calculator
{
    public function __construct(
        private readonly DatabaseCouponValidator $validator,
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

        $base = $calculation->itemsSubtotal()->plus($calculation->totalByType(AdjustmentType::Promotion));

        foreach ($codes as $code) {
            if ($base->isNegativeOrZero()) {
                break;
            }

            $coupon = $this->validator->find($code);

            if ($coupon === null) {
                continue;
            }

            try {
                $this->validator->assert($coupon, $code, $context + ['subtotal' => $base]);
            } catch (CommerceException) {
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
