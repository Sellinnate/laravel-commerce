<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing;

use Selli\Commerce\Contracts\GiftCardValidator;
use Selli\Commerce\Exceptions\GiftCardException;
use Selli\Commerce\Pricing\Models\GiftCard;

final class DatabaseGiftCardValidator implements GiftCardValidator
{
    public function validate(string $code, array $context = []): void
    {
        $giftCard = $this->find($code) ?? throw GiftCardException::notFound($code);

        if (! $giftCard->isRedeemable(now())) {
            throw GiftCardException::notRedeemable($code);
        }

        $currency = $context['currency'] ?? null;

        if (is_string($currency) && $giftCard->currency !== $currency) {
            throw GiftCardException::currencyMismatch($giftCard->currency, $currency);
        }
    }

    public function find(string $code): ?GiftCard
    {
        return GiftCard::query()->where('code', $code)->first();
    }
}
