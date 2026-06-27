<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\PriceFactory;

/**
 * A single price for a purchasable inside a {@see PriceBook}, optionally tiered
 * by a minimum quantity.
 *
 * @property string $id
 * @property string $price_book_id
 * @property string $purchasable_type
 * @property string $purchasable_id
 * @property int $amount
 * @property string $currency
 * @property int $min_quantity
 */
class Price extends Model
{
    /** @use HasFactory<PriceFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'prices';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'min_quantity' => 'integer',
        ];
    }

    protected $attributes = [
        'min_quantity' => 1,
    ];

    public function toMoney(): Money
    {
        return Money::ofMinor($this->amount, $this->currency);
    }

    /**
     * @return BelongsTo<PriceBook, $this>
     */
    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    protected static function newFactory(): PriceFactory
    {
        return PriceFactory::new();
    }
}
