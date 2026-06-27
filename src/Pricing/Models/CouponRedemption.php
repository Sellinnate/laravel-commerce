<?php

declare(strict_types=1);

namespace Selli\Commerce\Pricing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Selli\Commerce\Concerns\AppendOnly;
use Selli\Commerce\Concerns\BelongsToTenant;
use Selli\Commerce\Concerns\HasPrefixedTable;

/**
 * Append-only record of a coupon redemption, for usage-limit enforcement and
 * reconciliation.
 *
 * @property string $id
 * @property string $coupon_id
 * @property string|null $tenant_id
 * @property string|null $customer_type
 * @property string|null $customer_id
 * @property string|null $order_id
 * @property int $amount
 * @property string|null $currency
 * @property Carbon $created_at
 */
class CouponRedemption extends Model
{
    use AppendOnly;
    use BelongsToTenant;
    use HasPrefixedTable;
    use HasUlids;

    public const UPDATED_AT = null;

    protected string $baseTable = 'coupon_redemptions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
