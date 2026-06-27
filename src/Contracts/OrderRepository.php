<?php

declare(strict_types=1);

namespace Selli\Commerce\Contracts;

use Selli\Commerce\Order\Models\Order;

interface OrderRepository
{
    public function find(string $id): ?Order;

    public function findByNumber(string $number): ?Order;

    public function save(Order $order): Order;
}
