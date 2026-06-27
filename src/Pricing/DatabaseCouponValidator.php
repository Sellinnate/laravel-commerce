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
        $tenantId = is_string($context['tenant_id'] ?? null) ? $context['tenant_id'] : null;

        $this->assert($this->find($code, $tenantId) ?? throw CouponNotFoundException::for($code), $code, $context);
    }

    /**
     * Find a coupon by code scoped to the given tenant. Pricing must follow the
     * cart's tenant, not the ambient tenant context (which may be null during
     * guest checkout or system placement), so the scope is explicit here.
     */
    public function find(string $code, ?string $tenantId = null): ?Coupon
    {
        return Coupon::withoutTenantScope()
            ->where('code', $code)
            ->when(
                $tenantId === null,
                fn ($query) => $query->whereNull('tenant_id'),
                fn ($query) => $query->where('tenant_id', $tenantId),
            )
            ->first();
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

        if ($minimum === null || ! $subtotal instanceof Money) {
            return;
        }

        // Guard the currency explicitly so a misconfigured minimum in another
        // currency yields a typed coupon rejection, not a low-level Money error.
        if ($minimum->getCurrency()->getCurrencyCode() !== $subtotal->getCurrency()->getCurrencyCode()) {
            throw CouponCurrencyMismatchException::between(
                $minimum->getCurrency()->getCurrencyCode(),
                $subtotal->getCurrency()->getCurrencyCode(),
            );
        }

        if ($subtotal->isLessThan($minimum)) {
            throw CouponMinimumNotMetException::for($code, $minimum);
        }
    }
}
