<?php

declare(strict_types=1);

namespace Selli\Commerce\Cart;

use Brick\Money\Money;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Cart\Models\CartItem;
use Selli\Commerce\Contracts\CartRepository;
use Selli\Commerce\Contracts\CouponValidator;
use Selli\Commerce\Contracts\GiftCardValidator;
use Selli\Commerce\Contracts\PriceResolver;
use Selli\Commerce\Contracts\Purchasable;
use Selli\Commerce\Contracts\PurchasableResolver;
use Selli\Commerce\Enums\AdjustmentType;
use Selli\Commerce\Enums\CartStatus;
use Selli\Commerce\Enums\MergeStrategy;
use Selli\Commerce\Events\Cart\CartCleared;
use Selli\Commerce\Events\Cart\CartCreated;
use Selli\Commerce\Events\Cart\CartMerged;
use Selli\Commerce\Events\Cart\ItemAddedToCart;
use Selli\Commerce\Events\Cart\ItemRemovedFromCart;
use Selli\Commerce\Events\Cart\ItemUpdatedInCart;
use Selli\Commerce\Events\Pricing\CouponApplied;
use Selli\Commerce\Events\Pricing\CouponRejected;
use Selli\Commerce\Exceptions\CartItemMismatchException;
use Selli\Commerce\Exceptions\CartNotFoundException;
use Selli\Commerce\Exceptions\CartNotMutableException;
use Selli\Commerce\Exceptions\CommerceException;
use Selli\Commerce\Exceptions\CurrencyMismatchException;
use Selli\Commerce\Exceptions\InvalidQuantityException;
use Selli\Commerce\Exceptions\ProductNotAvailableException;
use Selli\Commerce\Order\Actions\PlaceOrder;

/**
 * The application service that orchestrates every cart operation: add, update,
 * setQuantity, remove, clear, merge and calculate. Validation is explicit and
 * raises typed domain exceptions — never silent failures.
 */
final class CartManager
{
    public function __construct(
        private readonly CartRepository $repository,
        private readonly PriceResolver $prices,
        private readonly PurchasableResolver $purchasables,
        private readonly CalculationBuilder $calculations,
        private readonly Dispatcher $events,
        private readonly CouponValidator $couponValidator,
        private readonly GiftCardValidator $giftCardValidator,
    ) {}

    public function find(string $id): ?Cart
    {
        return $this->repository->find($id);
    }

    public function create(?string $currency = null, ?string $ownerType = null, ?string $ownerId = null): Cart
    {
        $cart = new Cart([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'currency' => $currency ?? Config::string('commerce.default_currency', 'EUR'),
            'status' => CartStatus::Active,
            'expires_at' => $this->expiry(),
        ]);

        $this->repository->save($cart);
        $cart->setRelation('items', $cart->newCollection());

        $this->events->dispatch(new CartCreated($cart));

        return $cart;
    }

    /**
     * Find the active cart for an owner or create a fresh one.
     *
     * Under a race, two parallel requests may each create an active cart for
     * the same owner. This is intentionally tolerated rather than guarded by a
     * filtered unique index (which is not portable across SQLite/MySQL/
     * Postgres): duplicate active carts are harmless and reconciled by
     * {@see merge()} on the next login/checkout.
     */
    public function forOwner(string $ownerType, string $ownerId, ?string $currency = null): Cart
    {
        return $this->repository->findActiveForOwner($ownerType, $ownerId)
            ?? $this->create($currency, $ownerType, $ownerId);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $metadata
     */
    public function add(Cart $cart, Purchasable $purchasable, int $quantity = 1, array $options = [], array $metadata = []): CartItem
    {
        $this->assertMutable($cart);
        $this->assertQuantity($quantity);

        if (! $purchasable->isAvailable($quantity)) {
            throw ProductNotAvailableException::for($purchasable->getName(), $quantity);
        }

        $unitPrice = $this->prices->resolve($purchasable, $cart->currency, $this->context($cart, $quantity));
        $resolvedCurrency = $unitPrice->getCurrency()->getCurrencyCode();

        if ($resolvedCurrency !== $cart->currency) {
            throw CurrencyMismatchException::between($cart->currency, $resolvedCurrency);
        }

        return DB::transaction(function () use ($cart, $purchasable, $quantity, $options, $metadata, $unitPrice): CartItem {
            $this->lockActiveCart($cart);
            $cart->load('items');

            $existing = $this->idempotentMatch($cart, $purchasable, $options);

            if ($existing !== null) {
                $newQuantity = $existing->quantity + $quantity;

                $this->assertCartQuantityAvailable($cart, $purchasable, $purchasable->getPurchasableType(), $purchasable->getPurchasableId(), $purchasable->getName(), $newQuantity, $existing->id);

                $existing->quantity = $newQuantity;
                // Re-resolve the price against the combined quantity so a
                // quantity-tier price book reflects the merged line, not just
                // this increment.
                $existing->unit_price = $this->prices->resolve($purchasable, $cart->currency, $this->context($cart, $newQuantity));
                $existing->save();

                $cart->load('items');
                $this->touch($cart);
                $this->events->dispatch(new ItemUpdatedInCart($cart, $existing));

                return $existing;
            }

            $this->assertCartQuantityAvailable($cart, $purchasable, $purchasable->getPurchasableType(), $purchasable->getPurchasableId(), $purchasable->getName(), $quantity, null);

            /** @var CartItem $item */
            $item = $cart->items()->create([
                'purchasable_type' => $purchasable->getPurchasableType(),
                'purchasable_id' => $purchasable->getPurchasableId(),
                'name' => $purchasable->getName(),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'options' => $options,
                'metadata' => $metadata,
            ]);

            $cart->load('items');
            $this->touch($cart);
            $this->events->dispatch(new ItemAddedToCart($cart, $item));

            return $item;
        });
    }

    public function setQuantity(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        $this->assertMutable($cart);
        $this->assertBelongsToCart($cart, $item);
        $this->assertQuantity($quantity);

        $purchasable = $this->purchasables->resolve($item->purchasable_type, $item->purchasable_id);

        if ($purchasable !== null && ! $purchasable->isAvailable($quantity)) {
            throw ProductNotAvailableException::for($item->name, $quantity);
        }

        return DB::transaction(function () use ($cart, $item, $quantity, $purchasable): CartItem {
            $this->lockActiveCart($cart);
            $cart->load('items');

            $this->assertCartQuantityAvailable($cart, $purchasable, $item->purchasable_type, $item->purchasable_id, $item->name, $quantity, $item->id);

            $item->quantity = $quantity;

            // Re-resolve the price for the new quantity so crossing a price-book
            // quantity tier updates the unit price immediately.
            if ($purchasable !== null) {
                $item->unit_price = $this->prices->resolve($purchasable, $cart->currency, $this->context($cart, $quantity));
            }

            $item->save();
            $cart->load('items');

            $this->touch($cart);
            $this->events->dispatch(new ItemUpdatedInCart($cart, $item));

            return $item;
        });
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        $this->assertMutable($cart);
        $this->assertBelongsToCart($cart, $item);

        DB::transaction(function () use ($cart, $item): void {
            $this->lockActiveCart($cart);

            $item->delete();
            $cart->load('items');

            $this->touch($cart);
            $this->events->dispatch(new ItemRemovedFromCart($cart, $item));
        });
    }

    public function clear(Cart $cart): void
    {
        $this->assertMutable($cart);

        DB::transaction(function () use ($cart): void {
            $this->lockActiveCart($cart);
            $this->clearItems($cart);
        });
    }

    private function clearItems(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->load('items');

        $this->touch($cart);
        $this->events->dispatch(new CartCleared($cart));
    }

    /**
     * Merge a (typically guest) source cart into a target cart and mark the
     * source as merged.
     */
    public function merge(Cart $source, Cart $target, ?MergeStrategy $strategy = null): Cart
    {
        $this->assertMutable($source);
        $this->assertMutable($target);

        if ($source->is($target)) {
            return $target;
        }

        if ($source->currency !== $target->currency) {
            throw CurrencyMismatchException::between($target->currency, $source->currency);
        }

        $strategy ??= $this->defaultMergeStrategy();

        // The whole merge is atomic: a stock violation on any line rolls the
        // entire operation back, so login/retry flows never leave a half-merge.
        return DB::transaction(function () use ($source, $target, $strategy): Cart {
            $this->lockActiveCart($source);
            $this->lockActiveCart($target);
            $source->load('items');
            $target->load('items');

            foreach ($source->items as $sourceItem) {
                $match = $this->matchLine($target, $sourceItem->purchasable_type, $sourceItem->purchasable_id, $sourceItem->options ?? []);

                if ($match === null) {
                    $this->assertAvailableForMerge($sourceItem, $sourceItem->quantity);

                    $created = $target->items()->create([
                        'purchasable_type' => $sourceItem->purchasable_type,
                        'purchasable_id' => $sourceItem->purchasable_id,
                        'name' => $sourceItem->name,
                        'quantity' => $sourceItem->quantity,
                        'unit_price' => $this->mergedUnitPrice($target, $sourceItem, $sourceItem->quantity),
                        'options' => $sourceItem->options ?? [],
                        'metadata' => $sourceItem->metadata ?? [],
                    ]);

                    // Keep the in-memory collection in sync so a later source
                    // line with the same purchasable matches and combines
                    // instead of creating a duplicate.
                    $target->items->push($created);

                    continue;
                }

                $newQuantity = match ($strategy) {
                    MergeStrategy::Sum => $match->quantity + $sourceItem->quantity,
                    MergeStrategy::KeepHighestQuantity => max($match->quantity, $sourceItem->quantity),
                    MergeStrategy::Replace => $sourceItem->quantity,
                };

                $this->assertAvailableForMerge($sourceItem, $newQuantity);

                $match->quantity = $newQuantity;
                $match->unit_price = $this->mergedUnitPrice($target, $match, $newQuantity);
                $match->save();
            }

            // Carry over applied coupon / gift-card codes so a guest cart's
            // codes are not lost when it merges into the user cart at login.
            foreach (['coupons', 'gift_cards'] as $key) {
                $this->writeCodes($target, $key, array_values(array_unique(
                    array_merge($this->readCodes($target, $key), $this->readCodes($source, $key)),
                )));
            }

            // Carry the pricing segment so segment-specific prices survive the
            // login merge (the target keeps its own segment if it has one).
            $this->carryMetadataValue($source, $target, 'segment');

            $source->status = CartStatus::Merged;
            $source->save();

            $target->load('items');
            $this->touch($target);
            $this->events->dispatch(new CartMerged($target, $source));

            return $target;
        });
    }

    /**
     * Run the calculation pipeline over the cart (no persistence, lazy).
     */
    public function calculate(Cart $cart): Calculation
    {
        return $this->calculations->build($cart);
    }

    /**
     * Re-resolve live unit prices for every line, persist, then calculate.
     */
    public function recalculate(Cart $cart): Calculation
    {
        $this->assertMutable($cart);

        DB::transaction(function () use ($cart): void {
            $this->lockActiveCart($cart);
            $cart->load('items');

            foreach ($cart->items as $item) {
                $purchasable = $this->purchasables->resolve($item->purchasable_type, $item->purchasable_id);

                if ($purchasable === null) {
                    continue;
                }

                $item->unit_price = $this->prices->resolve($purchasable, $cart->currency, $this->context($cart, $item->quantity));
                $item->save();
            }

            $cart->load('items');
        });

        return $this->calculate($cart);
    }

    /**
     * Validate and apply a coupon code to the cart. Emits CouponApplied on
     * success, CouponRejected (and rethrows the typed exception) on failure.
     */
    public function applyCoupon(Cart $cart, string $code): void
    {
        $this->assertMutable($cart);

        $calculation = $this->calculate($cart);

        try {
            $this->couponValidator->validate($code, [
                'currency' => $cart->currency,
                'customer' => ['type' => $cart->owner_type, 'id' => $cart->owner_id],
                'tenant_id' => $cart->tenant_id,
                // Validate the minimum against the same base the calculator uses
                // (subtotal net of promotions), so a coupon accepted here is not
                // silently skipped at calculation time.
                'subtotal' => $calculation->itemsSubtotal()->plus($calculation->totalByType(AdjustmentType::Promotion)),
            ]);
        } catch (CommerceException $e) {
            $this->events->dispatch(new CouponRejected($cart, $code, $e->getMessage()));

            throw $e;
        }

        $this->storeCode($cart, 'coupons', $code);
        $this->events->dispatch(new CouponApplied($cart, $code));
    }

    public function removeCoupon(Cart $cart, string $code): void
    {
        $this->removeCode($cart, 'coupons', $code);
    }

    /**
     * @return list<string>
     */
    public function coupons(Cart $cart): array
    {
        return $this->readCodes($cart, 'coupons');
    }

    /**
     * Validate and apply a gift card code to the cart as a tender.
     */
    public function applyGiftCard(Cart $cart, string $code): void
    {
        $this->assertMutable($cart);

        $this->giftCardValidator->validate($code, [
            'currency' => $cart->currency,
            'tenant_id' => $cart->tenant_id,
        ]);

        $this->storeCode($cart, 'gift_cards', $code);
    }

    public function removeGiftCard(Cart $cart, string $code): void
    {
        $this->removeCode($cart, 'gift_cards', $code);
    }

    /**
     * @return list<string>
     */
    public function giftCards(Cart $cart): array
    {
        return $this->readCodes($cart, 'gift_cards');
    }

    private function storeCode(Cart $cart, string $key, string $code): void
    {
        DB::transaction(function () use ($cart, $key, $code): void {
            $this->lockActiveCart($cart);

            $list = $this->readCodes($cart, $key);

            if (! in_array($code, $list, true)) {
                $list[] = $code;
            }

            $this->writeCodes($cart, $key, $list);
            $this->touch($cart);
        });
    }

    private function removeCode(Cart $cart, string $key, string $code): void
    {
        $this->assertMutable($cart);

        DB::transaction(function () use ($cart, $key, $code): void {
            $this->lockActiveCart($cart);

            $list = array_values(array_filter(
                $this->readCodes($cart, $key),
                static fn (string $existing): bool => $existing !== $code,
            ));

            $this->writeCodes($cart, $key, $list);
            $this->touch($cart);
        });
    }

    /**
     * @return list<string>
     */
    private function readCodes(Cart $cart, string $key): array
    {
        $metadata = $cart->metadata ?? [];
        $list = $metadata[$key] ?? [];

        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /**
     * @param  list<string>  $codes
     */
    private function writeCodes(Cart $cart, string $key, array $codes): void
    {
        $metadata = $cart->metadata ?? [];
        $metadata[$key] = $codes;
        $cart->metadata = $metadata;
    }

    /**
     * Copy a metadata value from source to target on merge, unless the target
     * already has its own value for that key.
     */
    private function carryMetadataValue(Cart $source, Cart $target, string $key): void
    {
        $targetMetadata = $target->metadata ?? [];

        if (array_key_exists($key, $targetMetadata)) {
            return;
        }

        $sourceMetadata = $source->metadata ?? [];

        if (array_key_exists($key, $sourceMetadata)) {
            $targetMetadata[$key] = $sourceMetadata[$key];
            $target->metadata = $targetMetadata;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function idempotentMatch(Cart $cart, Purchasable $purchasable, array $options): ?CartItem
    {
        if (! Config::boolean('commerce.cart.idempotent_add', true)) {
            return null;
        }

        return $this->matchLine($cart, $purchasable->getPurchasableType(), $purchasable->getPurchasableId(), $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function matchLine(Cart $cart, string $type, string $id, array $options): ?CartItem
    {
        $canonical = $this->canonicalOptions($options);

        foreach ($cart->items as $item) {
            if ($item->purchasable_type === $type
                && (string) $item->purchasable_id === (string) $id
                && $this->canonicalOptions($item->options ?? []) === $canonical) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $options
     */
    private function canonicalOptions(array $options): string
    {
        $this->recursiveKsort($options);

        return (string) json_encode($options);
    }

    /**
     * @param  array<mixed>  $array
     */
    private function recursiveKsort(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }

    /**
     * Build the price-resolution context. Quantity drives price-book quantity
     * tiers; the segment (from cart metadata) drives segment-specific books.
     *
     * @return array<string, mixed>
     */
    private function context(Cart $cart, int $quantity = 1): array
    {
        $context = [
            'tenant_id' => $cart->tenant_id,
            'customer' => ['type' => $cart->owner_type, 'id' => $cart->owner_id],
            'cart' => $cart,
            'quantity' => $quantity,
        ];

        $segment = ($cart->metadata ?? [])['segment'] ?? null;

        if (is_string($segment) && $segment !== '') {
            $context['segment'] = $segment;
        }

        return $context;
    }

    private function assertMutable(Cart $cart): void
    {
        if ($cart->isExpired()) {
            throw CartNotMutableException::expired($cart->id);
        }

        if (! $cart->status->isMutable()) {
            throw CartNotMutableException::inStatus($cart->status);
        }
    }

    /**
     * Acquire a row lock on the cart and authoritatively re-check that it is
     * still mutable. Held until the surrounding transaction commits, this
     * serialises cart writes against {@see PlaceOrder},
     * so no line can be added to a cart while it is being converted.
     */
    private function lockActiveCart(Cart $cart): void
    {
        $locked = Cart::withoutTenantScope()
            ->whereKey($cart->id)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw CartNotFoundException::forMutation($cart->id);
        }

        if ($locked->isExpired()) {
            throw CartNotMutableException::expired($locked->id);
        }

        if (! $locked->status->isMutable()) {
            throw CartNotMutableException::inStatus($locked->status);
        }

        $cart->status = $locked->status;
        $cart->expires_at = $locked->expires_at;
        // Refresh metadata from the locked row so concurrent code-list updates
        // (coupons / gift cards) are read-modify-written off the latest state.
        $cart->metadata = $locked->metadata;
    }

    private function assertBelongsToCart(Cart $cart, CartItem $item): void
    {
        if ((string) $item->cart_id !== (string) $cart->id) {
            throw CartItemMismatchException::notInCart($item->id, $cart->id);
        }
    }

    /**
     * Assert, under the cart lock, that the live purchasable can satisfy the
     * TOTAL quantity of that purchasable across the whole cart once the target
     * line is set to $lineQuantity (or added, when $excludeItemId is null).
     * This catches both post-lock stock drops and quantity split across
     * multiple option-lines of the same purchasable. Unresolvable purchasables
     * (catalogue removed) are skipped.
     */
    private function assertCartQuantityAvailable(Cart $cart, ?Purchasable $purchasable, string $type, string $id, string $name, int $lineQuantity, ?string $excludeItemId): void
    {
        $purchasable ??= $this->purchasables->resolve($type, $id);

        if ($purchasable === null) {
            return;
        }

        $total = $lineQuantity;

        foreach ($cart->items as $item) {
            if ($excludeItemId !== null && $item->id === $excludeItemId) {
                continue;
            }

            if ($item->purchasable_type === $type && (string) $item->purchasable_id === (string) $id) {
                $total += $item->quantity;
            }
        }

        if (! $purchasable->isAvailable($total)) {
            throw ProductNotAvailableException::for($name, $total);
        }
    }

    /**
     * Re-resolve the unit price for a merged line at the given quantity so
     * price-book quantity tiers apply. Falls back to the source/existing price
     * when the catalogue row can no longer be resolved.
     */
    private function mergedUnitPrice(Cart $target, CartItem $item, int $quantity): Money
    {
        $purchasable = $this->purchasables->resolve($item->purchasable_type, $item->purchasable_id);

        if ($purchasable === null) {
            return $item->unit_price;
        }

        return $this->prices->resolve($purchasable, $target->currency, $this->context($target, $quantity));
    }

    /**
     * Throw if a live purchasable cannot satisfy the post-merge quantity.
     * Unresolvable purchasables (catalogue removed) are skipped.
     */
    private function assertAvailableForMerge(CartItem $item, int $quantity): void
    {
        $purchasable = $this->purchasables->resolve($item->purchasable_type, $item->purchasable_id);

        if ($purchasable !== null && ! $purchasable->isAvailable($quantity)) {
            throw ProductNotAvailableException::for($item->name, $quantity);
        }
    }

    private function assertQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw InvalidQuantityException::mustBePositive($quantity);
        }
    }

    private function touch(Cart $cart): void
    {
        $cart->expires_at = $this->expiry();
        $cart->save();
    }

    private function expiry(): ?Carbon
    {
        $ttl = Config::integer('commerce.cart.ttl', 0);

        return $ttl > 0 ? now()->addMinutes($ttl) : null;
    }

    private function defaultMergeStrategy(): MergeStrategy
    {
        $configured = Config::get('commerce.cart.merge_strategy', MergeStrategy::Sum);

        if ($configured instanceof MergeStrategy) {
            return $configured;
        }

        if (is_string($configured)) {
            return MergeStrategy::tryFrom($configured) ?? MergeStrategy::Sum;
        }

        return MergeStrategy::Sum;
    }
}
