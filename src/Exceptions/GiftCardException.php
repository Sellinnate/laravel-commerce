<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class GiftCardException extends CommerceException
{
    public static function notFound(string $code): self
    {
        return new self("Gift card [{$code}] does not exist.");
    }

    public static function notRedeemable(string $code): self
    {
        return new self("Gift card [{$code}] is not redeemable (inactive, empty or expired).");
    }

    public static function currencyMismatch(string $giftCardCurrency, string $cartCurrency): self
    {
        return new self("Gift card currency {$giftCardCurrency} does not match the cart currency {$cartCurrency}.");
    }

    public static function insufficientBalance(string $code): self
    {
        return new self("Gift card [{$code}] has insufficient balance for this redemption.");
    }
}
