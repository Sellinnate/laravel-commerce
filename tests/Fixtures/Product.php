<?php

declare(strict_types=1);

namespace Selli\Commerce\Tests\Fixtures;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Contracts\Purchasable;

/**
 * A host-application catalogue model made purchasable for the test suite — the
 * exact integration a real consumer performs.
 *
 * @property string $id
 * @property string $name
 * @property string|null $sku
 * @property int $price_cents
 * @property string $currency
 * @property bool $available
 * @property int|null $stock
 * @property array<string, mixed>|null $data
 */
class Product extends Model implements Purchasable
{
    use HasUlids;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'available' => 'boolean',
            'stock' => 'integer',
            'data' => 'array',
        ];
    }

    protected $attributes = [
        'currency' => 'EUR',
        'available' => true,
    ];

    public function getPurchasableId(): string
    {
        return (string) $this->getKey();
    }

    public function getPurchasableType(): string
    {
        return 'product';
    }

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function getUnitPrice(string $currency): Money
    {
        return Money::ofMinor($this->price_cents, $currency);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPurchasableData(): array
    {
        return array_merge(['sku' => $this->sku], $this->data ?? []);
    }

    public function isAvailable(int $quantity): bool
    {
        if (! $this->available) {
            return false;
        }

        return $this->stock === null || $quantity <= $this->stock;
    }
}
