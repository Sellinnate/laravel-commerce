<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Selli\Commerce\Contracts\OrderNumberGenerator;
use Selli\Commerce\Order\Models\OrderSequence;

/**
 * Default human-facing order number: a configurable prefix followed by a
 * zero-padded, per-tenant running sequence. Replaceable per project.
 *
 * The sequence is backed by a dedicated counter row, incremented under a
 * `lockForUpdate` row lock, so concurrent checkouts can never produce the same
 * number — including single-tenant mode, where `tenant_id` is null.
 */
final class SequentialOrderNumberGenerator implements OrderNumberGenerator
{
    public function generate(?string $tenantId): string
    {
        $prefix = Config::string('commerce.order.number_prefix', 'ORD-');
        $pad = Config::integer('commerce.order.number_pad', 6);
        $key = $tenantId ?? '__global__';

        return DB::transaction(function () use ($key, $tenantId, $prefix, $pad): string {
            // Create the counter row if absent without racing on the insert.
            OrderSequence::query()->insertOrIgnore([
                'tenant_key' => $key,
                'tenant_id' => $tenantId,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = OrderSequence::query()
                ->whereKey($key)
                ->lockForUpdate()
                ->firstOrFail();

            $number = $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->save();

            return $prefix.str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
        });
    }
}
