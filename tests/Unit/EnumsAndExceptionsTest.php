<?php

declare(strict_types=1);

use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Enums\CartStatus;
use Selli\Commerce\Exceptions\CartNotMutableException;
use Selli\Commerce\Exceptions\CurrencyMismatchException;
use Selli\Commerce\Exceptions\EmptyCartException;
use Selli\Commerce\Exceptions\InvalidQuantityException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;

it('classifies negative adjustment types', function (): void {
    expect(AdjustmentType::Discount->isNegative())->toBeTrue()
        ->and(AdjustmentType::Promotion->isNegative())->toBeTrue()
        ->and(AdjustmentType::GiftCard->isNegative())->toBeTrue()
        ->and(AdjustmentType::Tax->isNegative())->toBeFalse()
        ->and(AdjustmentType::Shipping->isNegative())->toBeFalse()
        ->and(AdjustmentType::Subtotal->isNegative())->toBeFalse()
        ->and(AdjustmentType::Fee->isNegative())->toBeFalse();
});

it('knows which cart statuses are mutable', function (): void {
    expect(CartStatus::Active->isMutable())->toBeTrue()
        ->and(CartStatus::Converted->isMutable())->toBeFalse()
        ->and(CartStatus::Merged->isMutable())->toBeFalse()
        ->and(CartStatus::Abandoned->isMutable())->toBeFalse()
        ->and(CartStatus::Expired->isMutable())->toBeFalse();
});

it('builds typed, explanatory exception messages', function (): void {
    expect(CurrencyMismatchException::between('EUR', 'USD')->getMessage())->toContain('USD')
        ->and(CartNotMutableException::inStatus(CartStatus::Converted)->getMessage())->toContain('converted')
        ->and(InvalidQuantityException::mustBePositive(0)->getMessage())->toContain('0')
        ->and(ProductNotAvailableException::for('Widget', 2)->getMessage())->toContain('Widget')
        ->and(EmptyCartException::cannotPlaceOrder()->getMessage())->toContain('empty');
});
