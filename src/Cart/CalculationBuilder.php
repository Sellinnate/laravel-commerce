<?php

declare(strict_types=1);

namespace Selli\Commerce\Cart;

use Selli\Commerce\Calculation\Calculation;
use Selli\Commerce\Calculation\CalculationLine;
use Selli\Commerce\Calculation\Pipeline;
use Selli\Commerce\Cart\Models\Cart;

/**
 * Turns a cart into a {@see Calculation} and runs it through the configured
 * pipeline. Pure: same cart + same rules → same totals.
 */
final class CalculationBuilder
{
    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

    public function build(Cart $cart): Calculation
    {
        $calculation = new Calculation($cart->currency, [
            'cart_id' => $cart->id,
            'tenant_id' => $cart->tenant_id,
            'customer' => ['type' => $cart->owner_type, 'id' => $cart->owner_id],
            'metadata' => $cart->metadata ?? [],
            'cart' => $cart,
        ]);

        foreach ($cart->items as $item) {
            $calculation->addLine(new CalculationLine(
                id: $item->id,
                purchasableType: $item->purchasable_type,
                purchasableId: $item->purchasable_id,
                name: $item->name,
                quantity: $item->quantity,
                unitPrice: $item->unit_price,
                options: $item->options ?? [],
                data: $item->metadata ?? [],
            ));
        }

        return $this->pipeline->process($calculation);
    }
}
