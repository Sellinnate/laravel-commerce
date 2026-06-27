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

        $relief = $this->relief($tax);

        $discountTotal = $calculation->totalByType(AdjustmentType::Discount)
            ->plus($calculation->totalByType(AdjustmentType::Promotion));

        // Exclusive price under relief: nothing to add — the price is already
        // net; one annotation records why no VAT was charged.
        if ($relief !== null && ! $inclusive) {
            $this->annotate($calculation, $relief['label'], $relief['data']);

            return;
        }

        foreach ($calculation->lines() as $line) {
            $category = $this->category($line, $defaultCategory);
            $rate = $this->resolver->resolve($category, $jurisdiction);

            if ($rate === null || $rate->isZero()) {
                continue;
            }

            $net = $line->subtotal()->plus($this->allocatedDiscount($discountTotal, $line->subtotal(), $itemsSubtotal));

            if ($net->isNegativeOrZero()) {
                continue;
            }

            if ($relief !== null) {
                // Inclusive price under relief: back out the embedded VAT so the
                // buyer actually pays the net amount, not the tax-inclusive one.
                $embedded = $this->rounding->round(
                    $net->multipliedBy($rate->basisPoints)->dividedBy(10000 + $rate->basisPoints, RoundingMode::HalfUp),
                );

                if (! $embedded->isZero()) {
                    $line->addAdjustment(new Adjustment(
                        AdjustmentType::Tax,
                        $relief['label'],
                        $embedded->multipliedBy(-1),
                        'tax',
                        true,
                        $relief['data'] + ['category' => $category, 'rate' => $rate->basisPoints, 'relief' => true],
                    ));
                }

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
                ['category' => $category, 'rate' => $rate->basisPoints, 'inclusive' => $inclusive],
            ));
        }
    }

    /**
     * The VAT relief (exemption or reverse charge) in effect, or null.
     *
     * @param  array<array-key, mixed>  $tax
     * @return array{label: string, data: array<string, mixed>}|null
     */
    private function relief(array $tax): ?array
    {
        if (($tax['exempt'] ?? null) === true) {
            return [
                'label' => 'Tax exempt',
                'data' => [
                    'exempt' => true,
                    'reason' => is_string($tax['exempt_reason'] ?? null) ? $tax['exempt_reason'] : null,
                ],
            ];
        }

        if ($this->isReverseCharge($tax)) {
            return [
                'label' => 'Reverse charge (no VAT)',
                'data' => [
                    'reverse_charge' => true,
                    'vat_number' => is_string($tax['vat_number'] ?? null) ? $tax['vat_number'] : null,
                ],
            ];
        }

        return null;
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
