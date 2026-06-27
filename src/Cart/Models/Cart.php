<?php

declare(strict_types=1);

namespace Selli\Commerce\Cart\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;
use Selli\Commerce\Database\Factories\CartFactory;
use Selli\Commerce\Enums\CartStatus;

/**
 * The cart aggregate: ephemeral and mutable, alive until it converts to an
 * order or expires.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $currency
 * @property CartStatus $status
 * @property array<string, mixed> $metadata
 * @property Carbon|null $expires_at
 * @property Collection<int, CartItem> $items
 */
class Cart extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use HasUlids;

    protected string $baseTable = 'carts';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'status' => CartStatus::Active->value,
        'metadata' => '{}',
    ];

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function isMutable(): bool
    {
        return $this->status->isMutable();
    }

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }
}
