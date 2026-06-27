<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Actions;

use Brick\Money\Money;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Selli\Commerce\Calculation\Adjustment;
use Selli\Commerce\Calculation\CalculationLine;
use Selli\Commerce\Cart\CartManager;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Contracts\OrderNumberGenerator;
use Selli\Commerce\Contracts\PurchasableResolver;
use Selli\Commerce\Contracts\RoundingStrategy;
use Selli\Commerce\Contracts\StockKeeper;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Enums\CartStatus;
use Selli\Commerce\Events\Order\OrderPlaced;
use Selli\Commerce\Exceptions\CartNotFoundException;
use Selli\Commerce\Exceptions\CartNotMutableException;
use Selli\Commerce\Exceptions\EmptyCartException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\Models\OrderLine;
use Selli\Commerce\Order\Models\OrderStateTransition;
use Selli\Commerce\Order\States\Pending;
use Selli\Commerce\Support\MoneyMath;

/**
 * The single transactional conversion of a cart into an order: run the final
 * calculation, freeze the snapshots and totals, persist the order, mark the
 * cart converted and emit {@see OrderPlaced} — all inside one DB transaction.
 * Either the order is born whole, or it is not born at all.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly CartManager $carts,
        private readonly OrderNumberGenerator $numbers,
        private readonly PurchasableResolver $purchasables,
        private readonly RoundingStrategy $rounding,
        private readonly Dispatcher $events,
        private readonly StockKeeper $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Extra order columns
     *                                            (billing_address, shipping_address, metadata, customer_*).
     */
    public function handle(Cart $cart, array $attributes = []): Order
    {
        if ($cart->isEmpty()) {
            throw EmptyCartException::cannotPlaceOrder();
        }

        if ($cart->status !== CartStatus::Active) {
            throw CartNotMutableException::inStatus($cart->status);
        }

        return DB::transaction(function () use ($cart, $attributes): Order {
            // Lock the cart row and re-check its status inside the transaction
            // so two concurrent checkouts of the same cart cannot both convert
            // it (the second blocks, then sees "converted" and aborts).
            $locked = Cart::withoutTenantScope()
                ->whereKey($cart->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw CartNotFoundException::forPlacement($cart->id);
            }

            if ($locked->status !== CartStatus::Active) {
                throw CartNotMutableException::inStatus($locked->status);
            }

            if ($locked->isExpired()) {
                throw CartNotMutableException::expired($locked->id);
            }

            // Take metadata (applied coupons / gift cards) from the locked row,
            // not the possibly-stale in-memory cart, so the totals reflect what
            // is actually persisted.
            $cart->metadata = $locked->metadata;
            $cart->load('items');

            // Re-check emptiness under the lock: a concurrent clear() could
            // have removed every line between the pre-check and the lock.
            if ($cart->isEmpty()) {
                throw EmptyCartException::cannotPlaceOrder();
            }

            // Re-validate availability at conversion time: stock may have
            // dropped since the items were added.
            $this->assertLinesAvailable($cart);

            $calculation = $this->carts->calculate($cart);

            // Caller attributes (addresses, metadata, customer) are applied
            // first; tenant, currency, totals, number and state are computed
            // authoritatively and can never be overridden by the caller.
            $order = new Order(array_merge([
                'customer_type' => $cart->owner_type,
                'customer_id' => $cart->owner_id,
            ], $attributes, [
                'tenant_id' => $cart->tenant_id,
                'currency' => $cart->currency,
                'subtotal' => $this->rounding->round($calculation->itemsSubtotal()),
                'discount_total' => $this->rounding->round($calculation->discountTotal()),
                'tax_total' => $this->rounding->round($calculation->taxTotal()),
                'shipping_total' => $this->rounding->round($calculation->shippingTotal()),
                'grand_total' => $calculation->grandTotal(),
                'placed_at' => now(),
            ]));

            $order->number = $this->numbers->generate($cart->tenant_id);
            $order->state = new Pending($order);
            $order->save();

            // Freeze the cart-level adjustments (coupons, promotions, gift
            // cards, fees) onto the order so listeners can record their usage
            // and the order keeps an explainable breakdown. `_adjustments` is a
            // server-owned key: any caller-supplied value is stripped so a
            // checkout cannot inject redemptions against other records.
            $adjustments = array_map(
                static fn (Adjustment $adjustment): array => $adjustment->toArray(),
                $calculation->adjustments(),
            );

            $metadata = $order->metadata ?? [];
            unset($metadata['_adjustments']);

            if ($adjustments !== []) {
                $metadata['_adjustments'] = $adjustments;
            }

            $order->metadata = $metadata;
            $order->save();

            // Cart-level discounts (coupons, promotions) are allocated to lines
            // in proportion to their subtotal so each order line reconciles with
            // the header totals.
            $cartDiscount = Money::zero($cart->currency);

            foreach ($calculation->adjustments() as $adjustment) {
                if (in_array($adjustment->type, [AdjustmentType::Discount, AdjustmentType::Promotion], true)) {
                    $cartDiscount = $cartDiscount->plus($adjustment->amount);
                }
            }

            $lines = $calculation->lines();
            $allocations = MoneyMath::allocate(
                $cartDiscount,
                array_map(static fn (CalculationLine $l): int => $l->subtotal()->getMinorAmount()->toInt(), $lines),
            );

            foreach ($lines as $index => $line) {
                $this->persistLine($order, $line, $allocations[$index]);
            }

            // Fulfil stock under the same transaction and the same lock: this is
            // where oversell is prevented by construction. A shortfall with the
            // backorder policy denying it throws and rolls the whole order back;
            // backordered lines are recorded truthfully on the frozen order.
            $backordered = $this->stock->fulfillOrder(
                $order->id,
                array_map(static fn (CalculationLine $l): array => [
                    'type' => $l->purchasableType,
                    'id' => $l->purchasableId,
                    'quantity' => $l->quantity,
                    'name' => $l->name,
                ], $lines),
                $order->tenant_id,
                $cart->id,
            );

            if ($backordered !== []) {
                $metadata = $order->metadata ?? [];
                $metadata['_backorders'] = $backordered;
                $order->metadata = $metadata;
                $order->save();
            }

            OrderStateTransition::query()->create([
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'from_state' => null,
                'to_state' => Pending::$name,
                'reason' => 'Order placed',
            ]);

            $locked->status = CartStatus::Converted;
            $locked->save();
            $cart->status = CartStatus::Converted;

            $order->load('lines');
            $this->events->dispatch(new OrderPlaced($order));

            return $order;
        });
    }

    private function assertLinesAvailable(Cart $cart): void
    {
        // Aggregate quantity per purchasable across all lines (including
        // separate option-lines) so availability reflects the total claimed on
        // shared inventory, consistent with CartManager's checks.
        /** @var array<string, array{type: string, id: string, name: string, quantity: int}> $totals */
        $totals = [];

        foreach ($cart->items as $item) {
            $key = $item->purchasable_type.'|'.$item->purchasable_id;

            if (! isset($totals[$key])) {
                $totals[$key] = [
                    'type' => $item->purchasable_type,
                    'id' => $item->purchasable_id,
                    'name' => $item->name,
                    'quantity' => 0,
                ];
            }

            $totals[$key]['quantity'] += $item->quantity;
        }

        foreach ($totals as $total) {
            $purchasable = $this->purchasables->resolve($total['type'], $total['id']);

            if ($purchasable !== null && ! $purchasable->isAvailable($total['quantity'])) {
                throw ProductNotAvailableException::for($total['name'], $total['quantity']);
            }
        }
    }

    private function persistLine(Order $order, CalculationLine $line, Money $allocated): void
    {
        $purchasable = $this->purchasables->resolve($line->purchasableType, $line->purchasableId);
        $snapshot = $purchasable?->getPurchasableData() ?? $line->data;

        $discount = $this->sumLineAdjustments($line, [AdjustmentType::Discount, AdjustmentType::Promotion])->plus($allocated);
        $tax = $this->sumLineAdjustments($line, [AdjustmentType::Tax]);

        // The breakdown must reconcile with the persisted discount_total, so the
        // allocated share of cart-level discounts is recorded in discount_detail
        // alongside the line-level adjustments — not silently folded into totals.
        $discountDetail = $this->adjustmentsToArray($line, [AdjustmentType::Discount, AdjustmentType::Promotion]);

        if (! $allocated->isZero()) {
            $discountDetail[] = [
                'type' => AdjustmentType::Discount->value,
                'label' => 'Cart discount (allocated)',
                'amount' => $this->rounding->round($allocated)->getMinorAmount()->toInt(),
                'currency' => $allocated->getCurrency()->getCurrencyCode(),
                'source' => 'cart_allocation',
                'affects_total' => true,
                'data' => ['allocated' => true],
            ];
        }

        $order->lines()->save(new OrderLine([
            'purchasable_type' => $line->purchasableType,
            'purchasable_id' => $line->purchasableId,
            'name' => $line->name,
            'sku' => is_string($snapshot['sku'] ?? null) ? $snapshot['sku'] : null,
            'quantity' => $line->quantity,
            'unit_price' => $line->unitPrice,
            'line_subtotal' => $this->rounding->round($line->subtotal()),
            'discount_total' => $this->rounding->round($discount),
            'tax_total' => $this->rounding->round($tax),
            'line_total' => $this->rounding->round($line->total()->plus($allocated)),
            'snapshot' => $snapshot,
            'tax_detail' => $this->adjustmentsToArray($line, [AdjustmentType::Tax]),
            'discount_detail' => $discountDetail,
        ]));
    }

    /**
     * @param  list<AdjustmentType>  $types
     */
    private function sumLineAdjustments(CalculationLine $line, array $types): Money
    {
        $total = $line->unitPrice->multipliedBy(0);

        foreach ($line->adjustments() as $adjustment) {
            if (in_array($adjustment->type, $types, true)) {
                $total = $total->plus($adjustment->amount);
            }
        }

        return $total;
    }

    /**
     * @param  list<AdjustmentType>  $types
     * @return list<array<string, mixed>>
     */
    private function adjustmentsToArray(CalculationLine $line, array $types): array
    {
        $out = [];

        foreach ($line->adjustments() as $adjustment) {
            if (in_array($adjustment->type, $types, true)) {
                $out[] = $adjustment->toArray();
            }
        }

        return $out;
    }
}
