<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Pricing\Models\Promotion;

/**
 * Pure evaluation of a promotion's rules against a calculation: do all its
 * conditions hold, and what discount do its actions yield. Kept side-effect
 * free and independent of persistence so it is trivially testable.
 */
final class PromotionEvaluator
{
    /**
     * Whether every condition on the promotion is satisfied by the calculation.
     */
    public function matches(Promotion $promotion, Calculation $calculation): bool
    {
        foreach ($promotion->conditions as $condition) {
            if (! $this->conditionHolds($condition, $calculation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The positive discount the promotion's actions yield against the given base.
     */
    public function discount(Promotion $promotion, Calculation $calculation, Money $base): Money
    {
        $discount = Money::zero($calculation->currency);

        foreach ($promotion->actions as $action) {
            $discount = $discount->plus($this->actionDiscount($action, $calculation, $base));
        }

        return $discount->isGreaterThan($base) ? $base : $discount;
    }

    /**
     * Whether the promotion grants free shipping.
     */
    public function grantsFreeShipping(Promotion $promotion): bool
    {
        foreach ($promotion->actions as $action) {
            if (($action['type'] ?? null) === 'free_shipping') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function conditionHolds(array $condition, Calculation $calculation): bool
    {
        return match ($condition['type'] ?? null) {
            'cart_subtotal_min' => $this->cartSubtotalAtLeast($condition, $calculation),
            'item_quantity_min' => $this->totalQuantity($calculation) >= $this->toInt($condition['quantity'] ?? 0),
            'has_purchasable' => $this->hasPurchasable($condition, $calculation),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function cartSubtotalAtLeast(array $condition, Calculation $calculation): bool
    {
        $currency = $condition['currency'] ?? $calculation->currency;

        if ($currency !== $calculation->currency) {
            return false;
        }

        $threshold = Money::ofMinor($this->toInt($condition['amount'] ?? 0), $calculation->currency);

        return $calculation->itemsSubtotal()->isGreaterThanOrEqualTo($threshold);
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function hasPurchasable(array $condition, Calculation $calculation): bool
    {
        $type = $this->toString($condition['purchasable_type'] ?? null);
        $id = $this->toString($condition['purchasable_id'] ?? null);

        foreach ($calculation->lines() as $line) {
            if ($line->purchasableType === $type && $line->purchasableId === $id) {
                return true;
            }
        }

        return false;
    }

    private function totalQuantity(Calculation $calculation): int
    {
        $total = 0;

        foreach ($calculation->lines() as $line) {
            $total += $line->quantity;
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function actionDiscount(array $action, Calculation $calculation, Money $base): Money
    {
        return match ($action['type'] ?? null) {
            'percentage_off' => $base->multipliedBy($this->toInt($action['percent'] ?? 0))->dividedBy(100, RoundingMode::HalfUp),
            'fixed_off' => $this->fixedDiscount($action, $calculation, $base),
            default => Money::zero($calculation->currency),
        };
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function fixedDiscount(array $action, Calculation $calculation, Money $base): Money
    {
        $currency = $action['currency'] ?? $calculation->currency;

        if ($currency !== $calculation->currency) {
            return Money::zero($calculation->currency);
        }

        $fixed = Money::ofMinor($this->toInt($action['amount'] ?? 0), $calculation->currency);

        return $fixed->isGreaterThan($base) ? $base : $fixed;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    private function toString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
