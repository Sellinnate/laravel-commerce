<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Repositories;

use Selli\Commerce\Contracts\OrderRepository;
use Selli\Commerce\Order\Models\Order;

final class EloquentOrderRepository implements OrderRepository
{
    public function find(string $id): ?Order
    {
        return Order::query()->with('lines')->find($id);
    }

    public function findByNumber(string $number): ?Order
    {
        return Order::query()->with('lines')->where('number', $number)->first();
    }

    public function save(Order $order): Order
    {
        $order->save();

        return $order;
    }
}
