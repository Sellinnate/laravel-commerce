<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Selli\Commerce\Enums\GiftCardTransactionType;
use Selli\Commerce\Events\Order\OrderPlaced;
use Selli\Commerce\Events\Pricing\GiftCardRedeemed;
use Selli\Commerce\Events\Pricing\PromotionApplied;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Pricing\Models\Coupon;
use Selli\Commerce\Pricing\Models\CouponRedemption;
use Selli\Commerce\Pricing\Models\GiftCard;
use Selli\Commerce\Pricing\Models\GiftCardTransaction;

/**
 * On OrderPlaced, records the usage of every pricing adjustment frozen onto the
 * order: increments coupon usage and writes a redemption, decrements gift card
 * balances (under a row lock) and writes a ledger entry, and emits the
 * corresponding domain events.
 */
final class RecordPricingUsage
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        if (! Config::boolean('commerce.modules.pricing', true)) {
            return;
        }

        $order = $event->order;

        foreach ($this->adjustments($order) as $adjustment) {
            $source = $adjustment['source'] ?? null;
            $data = is_array($adjustment['data'] ?? null) ? $adjustment['data'] : [];
            $rawAmount = $adjustment['amount'] ?? 0;
            $amount = abs(is_numeric($rawAmount) ? (int) $rawAmount : 0);
            $currency = is_string($adjustment['currency'] ?? null) ? $adjustment['currency'] : $order->currency;

            match ($source) {
                'coupon' => $this->recordCoupon($order, $data, $amount, $currency),
                'gift_card' => $this->recordGiftCard($order, $data, $amount, $currency),
                'promotion' => $this->recordPromotion($order, $data, $amount, $currency),
                default => null,
            };
        }
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function adjustments(Order $order): array
    {
        $metadata = $order->metadata ?? [];
        $adjustments = $metadata['_adjustments'] ?? [];

        if (! is_array($adjustments)) {
            return [];
        }

        return array_values(array_filter($adjustments, 'is_array'));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function recordCoupon(Order $order, array $data, int $amount, string $currency): void
    {
        $couponId = $data['coupon_id'] ?? null;

        if (! is_string($couponId)) {
            return;
        }

        DB::transaction(function () use ($order, $couponId, $amount, $currency): void {
            $coupon = $this->scopedToOrderTenant(Coupon::withoutTenantScope(), $order)
                ->whereKey($couponId)
                ->lockForUpdate()
                ->first();

            if (! $coupon instanceof Coupon) {
                return;
            }

            // Re-check the global limit under the lock so two concurrent
            // placements cannot both push usage past the cap.
            if ($coupon->hasReachedGlobalLimit()) {
                return;
            }

            $coupon->increment('usage_count');

            CouponRedemption::query()->create([
                'coupon_id' => $coupon->id,
                'tenant_id' => $order->tenant_id,
                'customer_type' => $order->customer_type,
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'amount' => $amount,
                'currency' => $currency,
            ]);
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function recordGiftCard(Order $order, array $data, int $amount, string $currency): void
    {
        $giftCardId = $data['gift_card_id'] ?? null;

        if (! is_string($giftCardId)) {
            return;
        }

        DB::transaction(function () use ($order, $giftCardId, $amount, $currency): void {
            $giftCard = $this->scopedToOrderTenant(GiftCard::withoutTenantScope(), $order)
                ->whereKey($giftCardId)
                ->lockForUpdate()
                ->first();

            if (! $giftCard instanceof GiftCard) {
                return;
            }

            $applied = min($amount, $giftCard->balance);

            if ($applied <= 0) {
                return;
            }

            $giftCard->balance -= $applied;
            $giftCard->save();

            GiftCardTransaction::query()->create([
                'gift_card_id' => $giftCard->id,
                'tenant_id' => $order->tenant_id,
                'type' => GiftCardTransactionType::Redeem,
                'amount' => $applied,
                'currency' => $currency,
                'order_id' => $order->id,
            ]);

            $this->events->dispatch(new GiftCardRedeemed($giftCard, $applied, $order->id));
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function recordPromotion(Order $order, array $data, int $amount, string $currency): void
    {
        $promotionId = $data['promotion_id'] ?? null;
        $name = $data['name'] ?? '';

        if (! is_string($promotionId)) {
            return;
        }

        $this->events->dispatch(new PromotionApplied(
            $order,
            $promotionId,
            is_string($name) ? $name : '',
            $amount,
            $currency,
        ));
    }

    /**
     * @param  Builder<Coupon>|Builder<GiftCard>  $query
     * @return Builder<Coupon>|Builder<GiftCard>
     */
    private function scopedToOrderTenant(Builder $query, Order $order): Builder
    {
        return $order->tenant_id === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $order->tenant_id);
    }
}
