<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing;

use Selli\Commerce\Contracts\GiftCardValidator;
use Selli\Commerce\Exceptions\PricingModuleDisabledException;

final class NullGiftCardValidator implements GiftCardValidator
{
    public function validate(string $code, array $context = []): void
    {
        throw PricingModuleDisabledException::make();
    }
}
