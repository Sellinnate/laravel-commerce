<?php

declare(strict_types=1);

namespace Selli\Commerce\Tax\Calculators;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Config;
use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Calculation\CalculationLine;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\RoundingStrategy;
use Selli\Commerce\Contracts\TaxResolver;
use Selli\Commerce\Enums\AdjustmentType;

/**
 * Applies tax per line for the cart's jurisdiction, getting inclusive vs
 * exclusive right by construction:
 *
 * - Inclusive (B2C / EU): the price already contains tax, so the tax is derived
 *   from the gross (amount × rate ÷ (1 + rate)) and recorded as an informational
 *   adjustment that does NOT add to the total again.
 * - Exclusive (B2B / US): the price is net, so the tax (amount × rate) is added
 *   on top of the total.
 *
 * Cart-level discounts are allocated to lines in proportion to their subtotal so
 * tax is computed on the discounted base. Exemptions and the B2B intra-EU
 * reverse charge short-circuit to a zero, annotated tax line.
 */
final class TaxCalculator implements Calculator
{
    public function __construct(
        private readonly TaxResolver $resolver,
        private readonly RoundingStrategy $rounding,
    ) {}

    public function apply(Calculation $calculation): void
    {
        $tax = $this->taxContext($calculation);

        if ($tax === null) {
            return;
        }

        if (($tax['exempt'] ?? null) === true) {
            $this->annotate($calculation, 'Tax exempt', [
                'exempt' => true,
                'reason' => is_string($tax['exempt_reason'] ?? null) ? $tax['exempt_reason'] : null,
            ]);

            return;
        }

        if ($this->isReverseCharge($tax)) {
            $this->annotate($calculation, 'Reverse charge (no VAT)', [
                'reverse_charge' => true,
                'vat_number' => is_string($tax['vat_number'] ?? null) ? $tax['vat_number'] : null,
            ]);

            return;
        }

        $itemsSubtotal = $calculation->itemsSubtotal();

        if ($itemsSubtotal->isZero()) {
            return;
        }

        $inclusive = Config::boolean('commerce.tax.prices_include_tax', true);
        $defaultCategory = Config::string('commerce.tax.default_category', 'standard');
        $jurisdiction = [
            'country' => $tax['country'],
            'region' => $tax['region'] ?? null,
            'tenant_id' => $calculation->context['tenant_id'] ?? null,
        ];

        $discountTotal = $calculation->totalByType(AdjustmentType::Discount)
            ->plus($calculation->totalByType(AdjustmentType::Promotion));

        foreach ($calculation->lines() as $line) {
            $rate = $this->resolver->resolve($this->category($line, $defaultCategory), $jurisdiction);

            if ($rate === null || $rate->isZero()) {
                continue;
            }

            $net = $line->subtotal()->plus($this->allocatedDiscount($discountTotal, $line->subtotal(), $itemsSubtotal));

            if ($net->isNegativeOrZero()) {
                continue;
            }

            $taxAmount = $inclusive
                ? $net->multipliedBy($rate->basisPoints)->dividedBy(10000 + $rate->basisPoints, RoundingMode::HalfUp)
                : $net->multipliedBy($rate->basisPoints)->dividedBy(10000, RoundingMode::HalfUp);

            $taxAmount = $this->rounding->round($taxAmount);

            if ($taxAmount->isZero()) {
                continue;
            }

            $line->addAdjustment(new Adjustment(
                AdjustmentType::Tax,
                $rate->label,
                $taxAmount,
                'tax',
                ! $inclusive,
                ['category' => $this->category($line, $defaultCategory), 'rate' => $rate->basisPoints, 'inclusive' => $inclusive],
            ));
        }
    }

    public function identifier(): string
    {
        return 'tax';
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function taxContext(Calculation $calculation): ?array
    {
        $metadata = $calculation->context['metadata'] ?? [];
        $tax = is_array($metadata) ? ($metadata['tax'] ?? null) : null;

        if (! is_array($tax) || ! is_string($tax['country'] ?? null) || $tax['country'] === '') {
            return null;
        }

        return $tax;
    }

    /**
     * @param  array<array-key, mixed>  $tax
     */
    private function isReverseCharge(array $tax): bool
    {
        return Config::boolean('commerce.tax.reverse_charge', true) && ($tax['reverse_charge'] ?? null) === true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function annotate(Calculation $calculation, string $label, array $data): void
    {
        $calculation->addAdjustment(new Adjustment(
            AdjustmentType::Tax,
            $label,
            $calculation->zero(),
            'tax',
            false,
            $data,
        ));
    }

    private function category(CalculationLine $line, string $default): string
    {
        $category = $line->data['tax_category'] ?? null;

        return is_string($category) && $category !== '' ? $category : $default;
    }

    private function allocatedDiscount(Money $discountTotal, Money $lineSubtotal, Money $itemsSubtotal): Money
    {
        if ($discountTotal->isZero()) {
            return $discountTotal;
        }

        return $discountTotal
            ->multipliedBy($lineSubtotal->getMinorAmount()->toInt())
            ->dividedBy($itemsSubtotal->getMinorAmount()->toInt(), RoundingMode::HalfUp);
    }
}
