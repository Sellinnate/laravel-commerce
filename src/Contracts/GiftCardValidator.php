<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Selli\Commerce\Exceptions\CommerceException;

/**
 * Validates a gift card code for a cart context, raising a typed exception when
 * it cannot be used. The core depends only on this contract; the Pricing module
 * binds the database-backed implementation.
 */
interface GiftCardValidator
{
    /**
     * @param  array<string, mixed>  $context  currency, tenant_id
     *
     * @throws CommerceException when the gift card cannot be used
     */
    public function validate(string $code, array $context = []): void;
}
