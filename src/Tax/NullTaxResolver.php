<?php

declare(strict_types=1);

namespace Selli\Commerce\Tax;

use Selli\Commerce\Contracts\TaxResolver;

/**
 * Resolves no rate at all — bound when the Tax module is disabled, so nothing
 * is taxed.
 */
final class NullTaxResolver implements TaxResolver
{
    public function resolve(string $category, array $jurisdiction): ?RateResult
    {
        return null;
    }
}
