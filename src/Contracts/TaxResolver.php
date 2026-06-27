<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Selli\Commerce\Tax\RateResult;

/**
 * Resolves the effective tax rate for a category in a jurisdiction. The core
 * ships a tabular resolver backed by the `tax_rates` table; an adapter can
 * delegate to an external VAT/sales-tax provider without changing the domain.
 */
interface TaxResolver
{
    /**
     * @param  array<string, mixed>  $jurisdiction  country, region, tenant_id
     */
    public function resolve(string $category, array $jurisdiction): ?RateResult;
}
