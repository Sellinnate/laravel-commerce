<?php

declare(strict_types=1);

namespace Selli\Commerce\Calculation;

use Brick\Money\Money;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Support\MoneyMath;

/**
 * The mutable accumulator threaded through the calculation pipeline. Each
 * calculator contributes lines and/or adjustments; the result is a total that
 * is justifiable line by line.
 */
final class Calculation
{
    /** @var list<CalculationLine> */
    private array $lines = [];

    /** @var list<Adjustment> */
    private array $adjustments = [];

    private ?Money $grandTotal = null;

    /**
     * @param  array<string, mixed>  $context  Free-form data shared with calculators
     *                                         (customer, tenant, addresses, flags).
     */
    public function __construct(
        public readonly string $currency,
        public array $context = [],
    ) {}

    public function addLine(CalculationLine $line): void
    {
        $this->lines[] = $line;
    }

    /**
     * @return list<CalculationLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Add a cart-level adjustment (e.g. a whole-cart discount or shipping).
     */
    public function addAdjustment(Adjustment $adjustment): void
    {
        $this->adjustments[] = $adjustment;
    }

    /**
     * @return list<Adjustment>
     */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    /**
     * Every adjustment, line-level and cart-level, in pipeline order.
     *
     * @return list<Adjustment>
     */
    public function allAdjustments(): array
    {
        $all = [];

        foreach ($this->lines as $line) {
            foreach ($line->adjustments() as $adjustment) {
                $all[] = $adjustment;
            }
        }

        foreach ($this->adjustments as $adjustment) {
            $all[] = $adjustment;
        }

        return $all;
    }

    public function zero(): Money
    {
        return Money::zero($this->currency);
    }

    /**
     * Σ (unit price × quantity) over all lines, before adjustments.
     */
    public function itemsSubtotal(): Money
    {
        return MoneyMath::sum(
            $this->currency,
            array_map(static fn (CalculationLine $l): Money => $l->subtotal(), $this->lines),
        );
    }

    /**
     * Sum of every total-affecting adjustment (line + cart level).
     */
    public function adjustmentsTotal(): Money
    {
        return MoneyMath::sum(
            $this->currency,
            array_map(
                static fn (Adjustment $a): Money => $a->amount,
                array_values(array_filter(
                    $this->allAdjustments(),
                    static fn (Adjustment $a): bool => $a->affectsTotal(),
                )),
            ),
        );
    }

    /**
     * Total of a given adjustment type, for reporting (ignores affectsTotal).
     */
    public function totalByType(AdjustmentType $type): Money
    {
        return MoneyMath::sum(
            $this->currency,
            array_map(
                static fn (Adjustment $a): Money => $a->amount,
                array_values(array_filter(
                    $this->allAdjustments(),
                    static fn (Adjustment $a): bool => $a->type === $type,
                )),
            ),
        );
    }

    public function discountTotal(): Money
    {
        return $this->totalByType(AdjustmentType::Discount)
            ->plus($this->totalByType(AdjustmentType::Promotion));
    }

    public function taxTotal(): Money
    {
        return $this->totalByType(AdjustmentType::Tax);
    }

    public function shippingTotal(): Money
    {
        return $this->totalByType(AdjustmentType::Shipping);
    }

    /**
     * The unrounded total before {@see GrandTotalCalculator} consolidates it.
     */
    public function rawGrandTotal(): Money
    {
        return $this->itemsSubtotal()->plus($this->adjustmentsTotal());
    }

    public function setGrandTotal(Money $grandTotal): void
    {
        $this->grandTotal = $grandTotal;
    }

    /**
     * The consolidated, rounded grand total. Falls back to the raw total when
     * the pipeline has not run a GrandTotalCalculator yet.
     */
    public function grandTotal(): Money
    {
        return $this->grandTotal ?? $this->rawGrandTotal();
    }

    /**
     * A structured, explainable breakdown of the whole calculation.
     *
     * @return array<string, mixed>
     */
    public function breakdown(): array
    {
        return [
            'currency' => $this->currency,
            'items_subtotal' => $this->itemsSubtotal()->getMinorAmount()->toInt(),
            'discount_total' => $this->discountTotal()->getMinorAmount()->toInt(),
            'tax_total' => $this->taxTotal()->getMinorAmount()->toInt(),
            'shipping_total' => $this->shippingTotal()->getMinorAmount()->toInt(),
            'grand_total' => $this->grandTotal()->getMinorAmount()->toInt(),
            'lines' => array_map(static fn (CalculationLine $l): array => $l->toArray(), $this->lines),
            'adjustments' => array_map(static fn (Adjustment $a): array => $a->toArray(), $this->adjustments),
        ];
    }
}
