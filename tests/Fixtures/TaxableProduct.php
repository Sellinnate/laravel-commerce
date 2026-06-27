<?php

declare(strict_types=1);

namespace Selli\Commerce\Tests\Fixtures;

use Selli\Commerce\Contracts\Taxable;

/**
 * A purchasable that also declares a tax category — the integration a host makes
 * to drive category-based tax rates.
 */
class TaxableProduct extends Product implements Taxable
{
    public function getTaxCategory(): ?string
    {
        return is_string($this->tax_category) ? $this->tax_category : null;
    }
}
