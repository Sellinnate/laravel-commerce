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
use Selli\Commerce\Support\MoneyMath;

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

        // Under relief no VAT is charged: record a zero, annotated tax line so
        // the breakdown carries "VAT 0" and the reason, and tax_total stays zero.
        if ($relief !== null) {
            $this->annotate($calculation, $relief['label'], $relief['data']);

            // Exclusive prices are already net — there is nothing to remove.
            if (! $inclusive) {
                return;
            }
        }

        // Only CART-level discounts are spread proportionally across lines; a
        // line's own discounts reduce that line's base alone. This mirrors how
        // PlaceOrder freezes per-line discounts, so the tax base always matches
        // the persisted breakdown even when a custom calculator emits a
        // line-level discount.
        $lines = $calculation->lines();
        $discountAllocations = MoneyMath::allocate(
            $this->cartLevelDiscount($calculation),
            array_map(static fn (CalculationLine $l): int => $l->subtotal()->getMinorAmount()->toInt(), $lines),
        );

        foreach ($lines as $index => $line) {
            $category = $this->category($line, $defaultCategory);
            $rate = $this->resolver->resolve($category, $jurisdiction);

            if ($rate === null || $rate->isZero()) {
                continue;
            }

            $net = $line->subtotal()
                ->plus($this->lineOwnDiscount($line))
                ->plus($discountAllocations[$index]);

            if ($net->isNegativeOrZero()) {
                continue;
            }

            if ($relief !== null) {
                // Inclusive price under relief: the catalogue price embeds VAT,
                // so remove it as a relief *discount* (not a negative tax). The
                // buyer pays the net amount while tax_total stays zero and the
                // gross subtotal still reconciles (gross − relief + 0 tax = net).
                $embedded = $this->rounding->round(
                    $net->multipliedBy($rate->basisPoints)->dividedBy(10000 + $rate->basisPoints, RoundingMode::HalfUp),
                );

                if (! $embedded->isZero()) {
                    $line->addAdjustment(new Adjustment(
                        AdjustmentType::Discount,
                        $relief['label'],
                        $embedded->multipliedBy(-1),
                        'tax_relief',
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
     * The B2B intra-EU reverse charge only applies when it is enabled, the
     * context asserts it, AND a VAT number is present — relief without a VAT
     * number is never granted, so a bare `reverse_charge: true` cannot zero out
     * VAT on its own. The host remains responsible for validating that number
     * (e.g. via VIES) and the buyer's eligibility.
     *
     * @param  array<array-key, mixed>  $tax
     */
    private function isReverseCharge(array $tax): bool
    {
        return Config::boolean('commerce.tax.reverse_charge', true)
            && ($tax['reverse_charge'] ?? null) === true
            && is_string($tax['vat_number'] ?? null)
            && $tax['vat_number'] !== '';
    }

    /**
     * Sum of cart-level discount and promotion adjustments (negative). These are
     * the whole-cart reductions allocated across lines for the tax base.
     */
    private function cartLevelDiscount(Calculation $calculation): Money
    {
        $total = $calculation->zero();

        foreach ($calculation->adjustments() as $adjustment) {
            if (in_array($adjustment->type, [AdjustmentType::Discount, AdjustmentType::Promotion], true)) {
                $total = $total->plus($adjustment->amount);
            }
        }

        return $total;
    }

    /**
     * Sum of a line's own discount and promotion adjustments (negative), which
     * reduce only that line's tax base.
     */
    private function lineOwnDiscount(CalculationLine $line): Money
    {
        $total = $line->subtotal()->multipliedBy(0);

        foreach ($line->adjustments() as $adjustment) {
            if (in_array($adjustment->type, [AdjustmentType::Discount, AdjustmentType::Promotion], true)) {
                $total = $total->plus($adjustment->amount);
            }
        }

        return $total;
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
}
