<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing;

use Brick\Money\Money;
use Selli\Commerce\Contracts\CouponValidator;
use Selli\Commerce\Enums\CouponType;
use Selli\Commerce\Exceptions\CouponCurrencyMismatchException;
use Selli\Commerce\Exceptions\CouponExpiredException;
use Selli\Commerce\Exceptions\CouponInactiveException;
use Selli\Commerce\Exceptions\CouponMinimumNotMetException;
use Selli\Commerce\Exceptions\CouponNotFoundException;
use Selli\Commerce\Exceptions\CouponUsageLimitReachedException;
use Selli\Commerce\Pricing\Models\Coupon;

/**
 * Centralised coupon validation: existence, active flag, validity window,
 * global and per-customer usage limits, currency match and minimum spend — each
 * failure is a distinct typed exception.
 */
final class DatabaseCouponValidator implements CouponValidator
{
    public function validate(string $code, array $context = []): void
    {
        $this->assert($this->find($code) ?? throw CouponNotFoundException::for($code), $code, $context);
    }

    /**
     * Find a coupon by code within the current tenant scope.
     */
    public function find(string $code): ?Coupon
    {
        return Coupon::query()->where('code', $code)->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function assert(Coupon $coupon, string $code, array $context = []): void
    {
        if (! $coupon->active) {
            throw CouponInactiveException::for($code);
        }

        $now = now();

        if ($coupon->starts_at !== null && $now->lessThan($coupon->starts_at)) {
            throw CouponExpiredException::notYetValid($code);
        }

        if ($coupon->expires_at !== null && $now->greaterThan($coupon->expires_at)) {
            throw CouponExpiredException::expired($code);
        }

        if ($coupon->hasReachedGlobalLimit()) {
            throw CouponUsageLimitReachedException::for($code);
        }

        $this->assertCustomerLimit($coupon, $code, $context);
        $this->assertCurrency($coupon, $context);
        $this->assertMinimum($coupon, $code, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertCustomerLimit(Coupon $coupon, string $code, array $context): void
    {
        if ($coupon->per_customer_limit === null) {
            return;
        }

        $customer = $context['customer'] ?? null;
        $customerId = is_array($customer) ? ($customer['id'] ?? null) : null;
        $customerType = is_array($customer) ? ($customer['type'] ?? null) : null;

        // A per-customer limit cannot be enforced for an anonymous guest, so a
        // coupon that declares one requires an identified customer rather than
        // being granted unlimited guest redemptions.
        if ($customerId === null) {
            throw CouponUsageLimitReachedException::requiresIdentification($code);
        }

        $count = $coupon->redemptions()
            ->where('customer_type', $customerType)
            ->where('customer_id', $customerId)
            ->count();

        if ($count >= $coupon->per_customer_limit) {
            throw CouponUsageLimitReachedException::forCustomer($code);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertCurrency(Coupon $coupon, array $context): void
    {
        $currency = $context['currency'] ?? null;

        if ($coupon->type === CouponType::Fixed
            && $coupon->currency !== null
            && is_string($currency)
            && $coupon->currency !== $currency) {
            throw CouponCurrencyMismatchException::between($coupon->currency, $currency);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertMinimum(Coupon $coupon, string $code, array $context): void
    {
        $minimum = $coupon->minimumAmount();
        $subtotal = $context['subtotal'] ?? null;

        if ($minimum !== null && $subtotal instanceof Money && $subtotal->isLessThan($minimum)) {
            throw CouponMinimumNotMetException::for($code, $minimum);
        }
    }
}
