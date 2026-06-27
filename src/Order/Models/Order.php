<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Selli\Commerce\Casts\MoneyCast;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\OrderFactory;
use Selli\Commerce\Order\States\OrderState;
use Spatie\ModelStates\HasStates;

/**
 * The order aggregate: persistent and authoritative. Once created, it is the
 * truth — totals are frozen, line snapshots are immutable.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $number
 * @property string|null $customer_type
 * @property string|null $customer_id
 * @property string $currency
 * @property OrderState $state
 * @property Money $subtotal
 * @property Money $discount_total
 * @property Money $tax_total
 * @property Money $shipping_total
 * @property Money|null $grand_total
 * @property array<string, mixed>|null $billing_address
 * @property array<string, mixed>|null $shipping_address
 * @property array<string, mixed> $metadata
 * @property Carbon|null $placed_at
 * @property Collection<int, OrderLine> $lines
 * @property Collection<int, OrderStateTransition> $transitions
 */
class Order extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasStates;
    use HasUlids;
    use SoftDeletes;

    protected string $baseTable = 'orders';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => OrderState::class,
            'subtotal' => MoneyCast::class,
            'discount_total' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'shipping_total' => MoneyCast::class,
            'grand_total' => MoneyCast::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'metadata' => 'array',
            'placed_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'metadata' => '{}',
    ];

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<OrderStateTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(OrderStateTransition::class);
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
