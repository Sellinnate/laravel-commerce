<?php

declare(strict_types=1);

namespace Selli\Commerce\Cart\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Selli\Commerce\Casts\MoneyCast;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Contracts\Purchasable;
use Selli\Commerce\Database\Factories\CartItemFactory;

/**
 * A single cart line: a polymorphic reference to a {@see Purchasable},
 * a quantity, the live-resolved unit price and free-form options/metadata.
 *
 * @property string $id
 * @property string $cart_id
 * @property string $purchasable_type
 * @property string $purchasable_id
 * @property string $name
 * @property int $quantity
 * @property Money $unit_price
 * @property array<string, mixed> $options
 * @property array<string, mixed> $metadata
 */
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'cart_items';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => MoneyCast::class,
            'options' => 'array',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'options' => '{}',
        'metadata' => '{}',
    ];

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }
}
