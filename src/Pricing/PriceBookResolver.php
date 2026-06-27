<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing;

use Brick\Money\Money;
use Illuminate\Support\Facades\Config;
use Selli\Commerce\Contracts\PriceResolver;
use Selli\Commerce\Contracts\Purchasable;
use Selli\Commerce\Pricing\Models\Price;
use Selli\Commerce\Pricing\Models\PriceBook;

/**
 * Resolves a purchasable's effective price from the price books: the most
 * specific valid book wins (exact customer segment over the default, then
 * higher priority, then the highest qualifying quantity tier, then the lowest
 * price). Falls back to the wrapped resolver (the purchasable's own price) when
 * no book applies.
 */
final class PriceBookResolver implements PriceResolver
{
    public function __construct(
        private readonly PriceResolver $fallback,
    ) {}

    public function resolve(Purchasable $purchasable, string $currency, array $context = []): Money
    {
        $segment = $this->segment($context);
        $quantity = $this->quantity($context);
        $now = now();

        $candidates = Price::query()
            ->with('priceBook')
            ->where('purchasable_type', $purchasable->getPurchasableType())
            ->where('purchasable_id', $purchasable->getPurchasableId())
            ->where('currency', $currency)
            ->where('min_quantity', '<=', $quantity)
            ->get()
            ->filter(function (Price $price) use ($currency, $segment, $now): bool {
                $book = $price->priceBook;

                return $book instanceof PriceBook
                    && $book->currency === $currency
                    && $book->isValidAt($now)
                    && ($book->segment === null || $book->segment === $segment);
            });

        $best = $candidates->sort(function (Price $a, Price $b) use ($segment): int {
            $bookA = $a->priceBook;
            $bookB = $b->priceBook;

            $specificityA = $bookA instanceof PriceBook && $bookA->segment === $segment ? 1 : 0;
            $specificityB = $bookB instanceof PriceBook && $bookB->segment === $segment ? 1 : 0;

            if ($specificityA !== $specificityB) {
                return $specificityB <=> $specificityA;
            }

            $priorityA = $bookA instanceof PriceBook ? $bookA->priority : 0;
            $priorityB = $bookB instanceof PriceBook ? $bookB->priority : 0;

            if ($priorityA !== $priorityB) {
                return $priorityB <=> $priorityA;
            }

            if ($a->min_quantity !== $b->min_quantity) {
                return $b->min_quantity <=> $a->min_quantity;
            }

            return $a->amount <=> $b->amount;
        })->first();

        if ($best instanceof Price) {
            return $best->toMoney();
        }

        return $this->fallback->resolve($purchasable, $currency, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function segment(array $context): string
    {
        $segment = $context['segment'] ?? null;

        if (is_string($segment) && $segment !== '') {
            return $segment;
        }

        return Config::string('commerce.pricing.default_segment', 'default');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function quantity(array $context): int
    {
        $quantity = $context['quantity'] ?? 1;

        return is_int($quantity) && $quantity > 0 ? $quantity : 1;
    }
}
