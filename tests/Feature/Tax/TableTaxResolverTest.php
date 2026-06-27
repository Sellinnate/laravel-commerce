<?php

declare(strict_types=1);

use Selli\Commerce\Contracts\TaxResolver;
use Selli\Commerce\Tax\Models\TaxRate;
use Selli\Commerce\Tax\NullTaxResolver;

it('resolves a country-wide rate', function (): void {
    TaxRate::factory()->create(['category' => 'standard', 'country' => 'IT', 'region' => null, 'rate' => 2200, 'name' => 'VAT 22%']);

    $rate = app(TaxResolver::class)->resolve('standard', ['country' => 'IT']);

    expect($rate?->basisPoints)->toBe(2200)
        ->and($rate?->label)->toBe('VAT 22%');
});

it('prefers a region-specific rate over the country-wide one', function (): void {
    TaxRate::factory()->create(['category' => 'standard', 'country' => 'US', 'region' => null, 'rate' => 0, 'name' => 'No state tax']);
    TaxRate::factory()->create(['category' => 'standard', 'country' => 'US', 'region' => 'CA', 'rate' => 725, 'name' => 'CA sales tax']);

    $rate = app(TaxResolver::class)->resolve('standard', ['country' => 'US', 'region' => 'CA']);

    expect($rate?->basisPoints)->toBe(725);
});

it('returns null when no rate matches', function (): void {
    expect(app(TaxResolver::class)->resolve('standard', ['country' => 'IT']))->toBeNull();
});

it('returns null without a country', function (): void {
    expect(app(TaxResolver::class)->resolve('standard', []))->toBeNull();
});

it('ignores an expired rate', function (): void {
    TaxRate::factory()->create(['category' => 'standard', 'country' => 'IT', 'rate' => 2200, 'ends_at' => now()->subDay()]);

    expect(app(TaxResolver::class)->resolve('standard', ['country' => 'IT']))->toBeNull();
});

it('does not resolve another tenant rate for a null-tenant jurisdiction', function (): void {
    TaxRate::factory()->create(['tenant_id' => 'other-tenant', 'category' => 'standard', 'country' => 'IT', 'rate' => 2200]);

    expect(app(TaxResolver::class)->resolve('standard', ['country' => 'IT']))->toBeNull();
});

it('the null resolver never resolves a rate', function (): void {
    expect((new NullTaxResolver)->resolve('standard', ['country' => 'IT']))->toBeNull();
});

it('exposes validity on the tax rate model', function (): void {
    $valid = TaxRate::factory()->create(['country' => 'IT', 'rate' => 2200]);
    $inactive = TaxRate::factory()->create(['country' => 'IT', 'rate' => 2200, 'active' => false]);
    $expired = TaxRate::factory()->create(['country' => 'IT', 'rate' => 2200, 'ends_at' => now()->subDay()]);

    expect($valid->isValidAt(now()))->toBeTrue()
        ->and($inactive->isValidAt(now()))->toBeFalse()
        ->and($expired->isValidAt(now()))->toBeFalse();
});
