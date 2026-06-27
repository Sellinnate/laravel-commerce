<?php

declare(strict_types=1);

namespace Selli\Commerce\Events\Cart;

use Selli\Commerce\Cart\Models\Cart;

final class CartMerged extends CartEvent
{
    public function __construct(Cart $cart, public readonly Cart $source)
    {
        parent::__construct($cart);
    }

    protected function payload(): array
    {
        return ['source_cart_id' => $this->source->id];
    }
}
