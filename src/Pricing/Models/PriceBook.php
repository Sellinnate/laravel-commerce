<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\PriceBookFactory;
use Selli\Commerce\Pricing\PriceBookResolver;

/**
 * A list of prices scoped by currency, customer segment and validity window —
 * launch prices, seasonal prices, segment prices. Resolved by the
 * {@see PriceBookResolver}.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $currency
 * @property string|null $segment
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $active
 */
class PriceBook extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PriceBookFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'price_books';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    protected $attributes = [
        'priority' => 0,
        'active' => true,
    ];

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function isValidAt(Carbon $moment): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->starts_at !== null && $moment->lessThan($this->starts_at)) {
            return false;
        }

        return $this->ends_at === null || $moment->lessThanOrEqualTo($this->ends_at);
    }

    /**
     * @param  Builder<PriceBook>  $query
     * @return Builder<PriceBook>
     */
    public function scopeValid(Builder $query, Carbon $moment): Builder
    {
        return $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $moment));
    }

    protected static function newFactory(): PriceBookFactory
    {
        return PriceBookFactory::new();
    }
}
