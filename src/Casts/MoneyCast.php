<?php

declare(strict_types=1);

namespace Selli\Commerce\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Serialises a {@see Money} value object across two columns:
 * `{key}_amount` (BIGINT minor units) and `{key}_currency` (CHAR(3)).
 *
 * Money is never stored as a float or an ambiguous decimal.
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $amount = $attributes[$key.'_amount'] ?? null;
        $currency = $attributes[$key.'_currency'] ?? null;

        if ($amount === null || $currency === null || $currency === '') {
            return null;
        }

        /** @var int|string $amount */
        /** @var string $currency */
        return Money::ofMinor((string) $amount, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key.'_amount' => null, $key.'_currency' => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException(
                sprintf('The [%s] attribute must be a %s instance.', $key, Money::class)
            );
        }

        return [
            $key.'_amount' => $value->getMinorAmount()->toInt(),
            $key.'_currency' => $value->getCurrency()->getCurrencyCode(),
        ];
    }
}
