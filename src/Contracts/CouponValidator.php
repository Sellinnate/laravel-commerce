<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Selli\Commerce\Exceptions\CommerceException;

/**
 * Validates a coupon code for a cart context, raising a typed exception when it
 * is not applicable. The core depends only on this contract; the Pricing module
 * binds the database-backed implementation (and a null implementation refuses
 * coupons when the module is disabled).
 */
interface CouponValidator
{
    /**
     * @param  array<string, mixed>  $context  currency, customer, tenant_id, subtotal (Money)
     *
     * @throws CommerceException when the coupon is not applicable
     */
    public function validate(string $code, array $context = []): void;
}
