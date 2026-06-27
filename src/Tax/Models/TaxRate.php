<?php

declare(strict_types=1);

namespace Selli\Commerce\Tax\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\TaxRateFactory;

/**
 * A tax rate for a category in a jurisdiction (country / optional region), with
 * a validity window. The rate is stored in basis points (2200 = 22.00%).
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $category
 * @property string $country
 * @property string|null $region
 * @property string $name
 * @property int $rate
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $active
 */
class TaxRate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'tax_rates';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
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
     * @param  Builder<TaxRate>  $query
     * @return Builder<TaxRate>
     */
    public function scopeValid(Builder $query, Carbon $moment): Builder
    {
        return $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $moment));
    }

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }
}
