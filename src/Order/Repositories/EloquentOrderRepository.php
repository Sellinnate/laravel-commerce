<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Repositories;

use Selli\Commerce\Contracts\OrderRepository;
use Selli\Commerce\Contracts\TenantContext;
use Selli\Commerce\Order\Models\Order;

final class EloquentOrderRepository implements OrderRepository
{
    public function __construct(
        private readonly TenantContext $tenants,
    ) {}

    public function find(string $id): ?Order
    {
        return Order::query()->with('lines')->find($id);
    }

    public function findByNumber(string $number): ?Order
    {
        // Order numbers are per-tenant sequences and therefore not globally
        // unique. The tenant global scope already constrains the query when a
        // tenant is active; with no tenant we must restrict to null-tenant
        // orders so we never return another tenant's order by number.
        $query = Order::query()->with('lines')->where('number', $number);

        if ($this->tenants->currentTenantId() === null) {
            $query->whereNull('tenant_id');
        }

        return $query->first();
    }

    public function save(Order $order): Order
    {
        $order->save();

        return $order;
    }
}
