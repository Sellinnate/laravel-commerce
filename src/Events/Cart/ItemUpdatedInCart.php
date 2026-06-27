<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Cart;

use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Cart\Models\CartItem;

final class ItemUpdatedInCart extends CartEvent
{
    public function __construct(Cart $cart, public readonly CartItem $item)
    {
        parent::__construct($cart);
    }

    protected function payload(): array
    {
        return [
            'item_id' => $this->item->id,
            'purchasable_type' => $this->item->purchasable_type,
            'purchasable_id' => $this->item->purchasable_id,
            'quantity' => $this->item->quantity,
        ];
    }
}
