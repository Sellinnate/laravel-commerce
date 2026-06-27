<?php

declare(strict_types=1);

namespace Selli\Commerce\Calculation;

use Brick\Money\Money;
use Selli\Commerce\Enums\AdjustmentType;

/**
 * An immutable, traceable contribution to a calculation: what happened, why,
 * how much, and whether it changes the payable total.
 *
 * Inclusive tax is recorded with {@see $affectsTotal} = false: it is already
 * contained in the line price, so it must be reported but not added again.
 */
final class Adjustment
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly AdjustmentType $type,
        public readonly string $label,
        public readonly Money $amount,
        public readonly string $source,
        public readonly bool $affectsTotal = true,
        public readonly array $data = [],
    ) {}

    public function affectsTotal(): bool
    {
        return $this->affectsTotal;
    }

    /**
     * @return array{type: string, label: string, amount: int, currency: string, source: string, affects_total: bool, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->label,
            'amount' => $this->amount->getMinorAmount()->toInt(),
            'currency' => $this->amount->getCurrency()->getCurrencyCode(),
            'source' => $this->source,
            'affects_total' => $this->affectsTotal,
            'data' => $this->data,
        ];
    }
}
