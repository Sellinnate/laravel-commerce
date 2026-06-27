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
        $tenantId = is_string($context['tenant_id'] ?? null) ? $context['tenant_id'] : null;
        $giftCard = $this->find($code, $tenantId) ?? throw GiftCardException::notFound($code);

        if (! $giftCard->isRedeemable(now())) {
            throw GiftCardException::notRedeemable($code);
        }

        $currency = $context['currency'] ?? null;

        if (is_string($currency) && $giftCard->currency !== $currency) {
            throw GiftCardException::currencyMismatch($giftCard->currency, $currency);
        }
    }

    /**
     * Find a gift card by code scoped to the given tenant (the cart's tenant),
     * independent of the ambient tenant context.
     */
    public function find(string $code, ?string $tenantId = null): ?GiftCard
    {
        return GiftCard::withoutTenantScope()
            ->where('code', $code)
            ->when(
                $tenantId === null,
                fn ($query) => $query->whereNull('tenant_id'),
                fn ($query) => $query->where('tenant_id', $tenantId),
            )
            ->first();
    }
}
