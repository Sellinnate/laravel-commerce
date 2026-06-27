<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Calculators;

use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\GiftCardValidator;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Exceptions\CommerceException;
use Selli\Commerce\Pricing\Models\GiftCard;

/**
 * Applies the cart's stored gift cards as a tender against the running payable
 * total (after promotions, discounts and tax). Eligibility is delegated to the
 * {@see GiftCardValidator} contract; the balance comes from the GiftCard
 * record. Each card is applied up to its balance and never beyond the remaining
 * total, so the grand total can never go negative.
 */
final class GiftCardCalculator implements Calculator
{
    public function __construct(
        private readonly GiftCardValidator $giftCards,
    ) {}

    public function apply(Calculation $calculation): void
    {
        $codes = $this->codes($calculation);

        if ($codes === []) {
            return;
        }

        $context = [
            'currency' => $calculation->currency,
            'tenant_id' => $calculation->context['tenant_id'] ?? null,
        ];

        $remaining = $calculation->rawGrandTotal();

        foreach ($codes as $code) {
            if ($remaining->isNegativeOrZero()) {
                break;
            }

            try {
                $this->giftCards->validate($code, $context);
            } catch (CommerceException) {
                continue;
            }

            $giftCard = GiftCard::query()->where('code', $code)->first();

            if (! $giftCard instanceof GiftCard || $giftCard->currency !== $calculation->currency) {
                continue;
            }

            $balance = $giftCard->balanceMoney();
            $applied = $balance->isLessThan($remaining) ? $balance : $remaining;

            if ($applied->isZero()) {
                continue;
            }

            $calculation->addAdjustment(new Adjustment(
                AdjustmentType::GiftCard,
                "Gift card {$code}",
                $applied->multipliedBy(-1),
                'gift_card',
                true,
                ['code' => $code, 'gift_card_id' => $giftCard->id],
            ));

            $remaining = $remaining->minus($applied);
        }
    }

    public function identifier(): string
    {
        return 'gift_card';
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

        $codes = $metadata['gift_cards'] ?? [];

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_filter($codes, 'is_string'));
    }
}
