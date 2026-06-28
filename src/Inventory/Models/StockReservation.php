<?php

declare(strict_types=1);

namespace Selli\Commerce\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\StockReservationFactory;
use Selli\Commerce\Enums\ReservationStatus;

/**
 * A hold on stock for a cart (while shopping) or an order (once placed). Active
 * reservations count against available-to-promise. A reservation with a passed
 * `expires_at` is treated as released for availability and swept by the
 * release-expired command.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $warehouse_id
 * @property string $purchasable_type
 * @property string $purchasable_id
 * @property int $quantity
 * @property ReservationStatus $status
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property Carbon|null $expires_at
 */
class StockReservation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<StockReservationFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'stock_reservations';

    protected $guarded = [];

    protected $attributes = [
        'status' => ReservationStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function isExpiredAt(Carbon $moment): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($moment);
    }

    /**
     * Active reservations that still hold stock at the given moment (not expired).
     *
     * @param  Builder<StockReservation>  $query
     * @return Builder<StockReservation>
     */
    public function scopeHolding(Builder $query, Carbon $moment): Builder
    {
        return $query->where('status', ReservationStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $moment));
    }

    protected static function newFactory(): StockReservationFactory
    {
        return StockReservationFactory::new();
    }
}
