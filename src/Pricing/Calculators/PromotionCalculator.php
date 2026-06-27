<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Calculators;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\RoundingStrategy;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Enums\StackingPolicy;
use Selli\Commerce\Pricing\Models\Promotion;
use Selli\Commerce\Pricing\PromotionEvaluator;

/**
 * Evaluates the tenant's active promotions against the cart and applies the
 * matching ones according to their declared stacking policy and priority.
 * Discounts never push the subtotal below zero.
 */
final class PromotionCalculator implements Calculator
{
    public function __construct(
        private readonly PromotionEvaluator $evaluator,
        private readonly RoundingStrategy $rounding,
    ) {}

    public function apply(Calculation $calculation): void
    {
        if ($calculation->isEmpty()) {
            return;
        }

        /** @var list<Promotion> $matched */
        $matched = [];

        foreach ($this->promotions() as $promotion) {
            if ($this->evaluator->matches($promotion, $calculation)) {
                $matched[] = $promotion;
            }
        }

        if ($matched === []) {
            return;
        }

        $base = $calculation->itemsSubtotal();

        foreach ($this->chooseBestSet($matched, $calculation) as $promotion) {
            $discount = $this->rounding->round($this->evaluator->discount($promotion, $calculation, $base));

            if (! $discount->isZero()) {
                $calculation->addAdjustment(new Adjustment(
                    AdjustmentType::Promotion,
                    $promotion->name,
                    $discount->multipliedBy(-1),
                    'promotion',
                    true,
                    ['promotion_id' => $promotion->id, 'name' => $promotion->name],
                ));

                $base = $base->minus($discount);
            }

            if ($this->evaluator->grantsFreeShipping($promotion)) {
                $calculation->addAdjustment(new Adjustment(
                    AdjustmentType::Shipping,
                    "{$promotion->name} (free shipping)",
                    Money::zero($calculation->currency),
                    'promotion',
                    false,
                    ['promotion_id' => $promotion->id, 'free_shipping' => true],
                ));
            }
        }
    }

    public function identifier(): string
    {
        return 'promotion';
    }

    /**
     * @return Collection<int, Promotion>
     */
    private function promotions()
    {
        return Promotion::query()->valid(now())->orderByDesc('priority')->get();
    }

    /**
     * Pick the valid application set with the largest *actual* total discount:
     * the cumulative stack (applied sequentially on a shrinking base, exactly as
     * apply() does), and each exclusive/best-of promotion on its own. Declared
     * constraints (exclusive/best-of never stack) are respected; a better
     * exclusive/best-of offer is never dropped in favour of a smaller stack.
     *
     * @param  list<Promotion>  $matched
     * @return list<Promotion>
     */
    private function chooseBestSet(array $matched, Calculation $calculation): array
    {
        /** @var list<list<Promotion>> $candidates */
        $candidates = [];

        $cumulative = array_values(array_filter(
            $matched,
            static fn (Promotion $promotion): bool => $promotion->stacking === StackingPolicy::Cumulative,
        ));

        if ($cumulative !== []) {
            $candidates[] = $cumulative;
        }

        foreach ($matched as $promotion) {
            if ($promotion->stacking !== StackingPolicy::Cumulative) {
                $candidates[] = [$promotion];
            }
        }

        $best = [];
        $bestTotal = -1;

        foreach ($candidates as $candidate) {
            $total = $this->totalDiscount($candidate, $calculation);

            if ($total > $bestTotal) {
                $bestTotal = $total;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * The real total discount (in minor units) a set yields when applied
     * sequentially on a shrinking base — identical to how apply() applies it.
     *
     * @param  list<Promotion>  $set
     */
    private function totalDiscount(array $set, Calculation $calculation): int
    {
        $base = $calculation->itemsSubtotal();
        $total = 0;

        foreach ($set as $promotion) {
            $discount = $this->rounding->round($this->evaluator->discount($promotion, $calculation, $base));
            $total += $discount->getMinorAmount()->toInt();
            $base = $base->minus($discount);
        }

        return $total;
    }
}
