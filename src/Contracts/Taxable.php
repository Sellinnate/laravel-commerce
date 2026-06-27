<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

/**
 * Optional contract a {@see Purchasable} may also implement to declare its tax
 * category (e.g. "standard", "reduced", "exempt"). The effective rate is then
 * category × jurisdiction, resolved by the {@see TaxResolver}. Purchasables that
 * do not implement it fall back to the configured default category.
 */
interface Taxable
{
    public function getTaxCategory(): ?string;
}
