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

        /** @var list<array{promotion: Promotion, discount: Money}> $matched */
        $matched = [];

        foreach ($this->promotions() as $promotion) {
            if (! $this->evaluator->matches($promotion, $calculation)) {
                continue;
            }

            $matched[] = [
                'promotion' => $promotion,
                'discount' => $this->rounding->round($this->evaluator->discount($promotion, $calculation, $calculation->itemsSubtotal())),
            ];
        }

        if ($matched === []) {
            return;
        }

        usort($matched, function (array $a, array $b): int {
            $byPriority = $b['promotion']->priority <=> $a['promotion']->priority;

            if ($byPriority !== 0) {
                return $byPriority;
            }

            return $b['discount']->getMinorAmount()->toInt() <=> $a['discount']->getMinorAmount()->toInt();
        });

        $base = $calculation->itemsSubtotal();

        foreach ($this->resolveStacking($matched) as $entry) {
            $promotion = $entry['promotion'];
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
     * @param  list<array{promotion: Promotion, discount: Money}>  $matched
     * @return list<array{promotion: Promotion, discount: Money}>
     */
    private function resolveStacking(array $matched): array
    {
        return match ($matched[0]['promotion']->stacking) {
            StackingPolicy::Exclusive => [$matched[0]],
            StackingPolicy::BestOf => [$this->bestByDiscount($matched)],
            StackingPolicy::Cumulative => array_values(array_filter(
                $matched,
                static fn (array $entry): bool => $entry['promotion']->stacking === StackingPolicy::Cumulative,
            )),
        };
    }

    /**
     * @param  list<array{promotion: Promotion, discount: Money}>  $matched
     * @return array{promotion: Promotion, discount: Money}
     */
    private function bestByDiscount(array $matched): array
    {
        $best = $matched[0];

        foreach ($matched as $entry) {
            if ($entry['discount']->isGreaterThan($best['discount'])) {
                $best = $entry;
            }
        }

        return $best;
    }
}
