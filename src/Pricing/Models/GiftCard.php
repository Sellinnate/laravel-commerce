<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\GiftCardFactory;

/**
 * Prepaid balance used as a tender, scaled off the payable total. Tracked
 * separately (with an append-only transaction ledger) for reconciliation.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $code
 * @property int $initial_amount
 * @property int $balance
 * @property string $currency
 * @property bool $active
 * @property Carbon|null $expires_at
 */
class GiftCard extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GiftCardFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'gift_cards';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_amount' => 'integer',
            'balance' => 'integer',
            'active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return HasMany<GiftCardTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }

    public function balanceMoney(): Money
    {
        return Money::ofMinor($this->balance, $this->currency);
    }

    public function isRedeemable(Carbon $moment): bool
    {
        if (! $this->active || $this->balance <= 0) {
            return false;
        }

        return $this->expires_at === null || $moment->lessThanOrEqualTo($this->expires_at);
    }
}
