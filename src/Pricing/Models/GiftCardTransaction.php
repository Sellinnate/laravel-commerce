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
use Selli\Commerce\Enums\GiftCardTransactionType;

/**
 * Append-only ledger entry for a gift card movement (issue / redeem / refund).
 *
 * @property string $id
 * @property string $gift_card_id
 * @property string|null $tenant_id
 * @property GiftCardTransactionType $type
 * @property int $amount
 * @property string $currency
 * @property string|null $order_id
 * @property Carbon $created_at
 */
class GiftCardTransaction extends Model
{
    use AppendOnly;
    use BelongsToTenant;
    use HasPrefixedTable;
    use HasUlids;

    public const UPDATED_AT = null;

    protected string $baseTable = 'gift_card_transactions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GiftCardTransactionType::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<GiftCard, $this>
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
}
