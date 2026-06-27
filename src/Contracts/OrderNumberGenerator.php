<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

/**
 * Produces the human-facing order number. Replaceable per project
 * (e.g. per-tenant sequences, year prefixes, branch codes).
 */
interface OrderNumberGenerator
{
    public function generate(?string $tenantId): string;
}
