<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\PromotionFactory;
use Selli\Commerce\Enums\StackingPolicy;

/**
 * A rule-based promotion: a set of conditions that, when all met, apply a set
 * of actions (contributions into the calculation). Combinability is governed by
 * an explicit {@see StackingPolicy} and a priority.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property int $priority
 * @property StackingPolicy $stacking
 * @property array<int, array<string, mixed>> $conditions
 * @property array<int, array<string, mixed>> $actions
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $active
 */
class Promotion extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'promotions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'stacking' => StackingPolicy::class,
            'conditions' => 'array',
            'actions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    protected $attributes = [
        'priority' => 0,
        'stacking' => 'cumulative',
        'conditions' => '[]',
        'actions' => '[]',
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
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeValid(Builder $query, Carbon $moment): Builder
    {
        return $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $moment));
    }

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }
}
