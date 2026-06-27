<?php

declare(strict_types=1);

use Brick\Money\Money;
use Selli\Commerce\Support\MoneyMath;

it('sums money values, returning zero for an empty set', function (): void {
    expect(MoneyMath::sum('EUR', [])->getMinorAmount()->toInt())->toBe(0)
        ->and(MoneyMath::sum('EUR', [Money::of(1, 'EUR'), Money::of('2.50', 'EUR')])->getMinorAmount()->toInt())->toBe(350);
});

it('allocates exactly, distributing the rounding remainder', function (): void {
    // −10.00 over three equal weights: 333/333/333 = 999, remainder −1 on the
    // first part so the split sums back to −1000.
    $parts = MoneyMath::allocate(Money::of('-10.00', 'EUR'), [3333, 3333, 3333]);

    $minor = array_map(fn (Money $m): int => $m->getMinorAmount()->toInt(), $parts);

    expect($minor)->toBe([-334, -333, -333])
        ->and(array_sum($minor))->toBe(-1000);
});

it('returns all zeros when the amount is zero', function (): void {
    $parts = MoneyMath::allocate(Money::zero('EUR'), [10, 20, 30]);

    expect(array_map(fn (Money $m): int => $m->getMinorAmount()->toInt(), $parts))->toBe([0, 0, 0]);
});

it('splits equally when every weight is zero so the amount is never dropped', function (): void {
    // No positive weight to spread across (e.g. all line subtotals are zero):
    // the amount must still be fully allocated, not silently lost.
    $parts = MoneyMath::allocate(Money::of('9.00', 'EUR'), [0, 0, 0]);

    $minor = array_map(fn (Money $m): int => $m->getMinorAmount()->toInt(), $parts);

    expect($minor)->toBe([300, 300, 300])
        ->and(array_sum($minor))->toBe(900);
});

it('returns an empty list for no weights', function (): void {
    expect(MoneyMath::allocate(Money::of(5, 'EUR'), []))->toBe([]);
});
