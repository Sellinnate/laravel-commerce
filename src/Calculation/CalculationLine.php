<?php

declare(strict_types=1);

namespace Selli\Commerce\Calculation;

use Brick\Money\Money;
use Selli\Commerce\Support\MoneyMath;

/**
 * One line within a {@see Calculation}: a purchasable, a quantity, a live unit
 * price, and the ordered adjustments contributed at line level (e.g. a line
 * promotion or per-line tax).
 */
final class CalculationLine
{
    /** @var list<Adjustment> */
    private array $adjustments = [];

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $purchasableType,
        public readonly string $purchasableId,
        public readonly string $name,
        public readonly int $quantity,
        public readonly Money $unitPrice,
        public readonly array $options = [],
        public readonly array $data = [],
    ) {}

    public function currency(): string
    {
        return $this->unitPrice->getCurrency()->getCurrencyCode();
    }

    /**
     * Line subtotal before adjustments: unit price × quantity.
     */
    public function subtotal(): Money
    {
        return $this->unitPrice->multipliedBy($this->quantity);
    }

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
     * Sum of the adjustments on this line that affect the total.
     */
    public function adjustmentsTotal(): Money
    {
        return MoneyMath::sum(
            $this->currency(),
            array_map(
                static fn (Adjustment $a): Money => $a->amount,
                array_values(array_filter(
                    $this->adjustments,
                    static fn (Adjustment $a): bool => $a->affectsTotal(),
                )),
            ),
        );
    }

    /**
     * Line total: subtotal plus its total-affecting adjustments.
     */
    public function total(): Money
    {
        return $this->subtotal()->plus($this->adjustmentsTotal());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'purchasable_type' => $this->purchasableType,
            'purchasable_id' => $this->purchasableId,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->getMinorAmount()->toInt(),
            'currency' => $this->currency(),
            'subtotal' => $this->subtotal()->getMinorAmount()->toInt(),
            'options' => $this->options,
            'adjustments' => array_map(static fn (Adjustment $a): array => $a->toArray(), $this->adjustments),
        ];
    }
}
