<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Support;

use Illuminate\Support\Facades\Config;
use Selli\Commerce\Contracts\OrderNumberGenerator;
use Selli\Commerce\Order\Models\Order;

/**
 * Default human-facing order number: a configurable prefix followed by a
 * zero-padded, per-tenant running sequence. Replaceable per project.
 *
 * Runs inside the PlaceOrder transaction; the `number` column is unique, so a
 * colliding concurrent insert fails the transaction rather than duplicating.
 */
final class SequentialOrderNumberGenerator implements OrderNumberGenerator
{
    public function generate(?string $tenantId): string
    {
        $prefix = Config::string('commerce.order.number_prefix', 'ORD-');
        $pad = Config::integer('commerce.order.number_pad', 6);

        $query = Order::withoutTenantScope()->withTrashed();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $next = $query->count() + 1;

        return $prefix.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }
}
