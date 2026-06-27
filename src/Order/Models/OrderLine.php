<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Selli\Commerce\Casts\MoneyCast;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\OrderLineFactory;

/**
 * An immutable snapshot of a purchasable at the moment it was ordered: name,
 * sku, frozen unit price and the host-supplied snapshot data. The order lives
 * off this snapshot even if the catalogue later changes or the product is
 * deleted.
 *
 * @property string $id
 * @property string $order_id
 * @property string $purchasable_type
 * @property string $purchasable_id
 * @property string $name
 * @property string|null $sku
 * @property int $quantity
 * @property Money $unit_price
 * @property Money $line_subtotal
 * @property Money $discount_total
 * @property Money $tax_total
 * @property Money $line_total
 * @property array<string, mixed> $snapshot
 * @property array<int, mixed> $tax_detail
 * @property array<int, mixed> $discount_detail
 * @property array<string, mixed> $metadata
 */
class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'order_lines';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => MoneyCast::class,
            'line_subtotal' => MoneyCast::class,
            'discount_total' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'snapshot' => 'array',
            'tax_detail' => 'array',
            'discount_detail' => 'array',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'snapshot' => '{}',
        'tax_detail' => '[]',
        'discount_detail' => '[]',
        'metadata' => '{}',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function newFactory(): OrderLineFactory
    {
        return OrderLineFactory::new();
    }
}
