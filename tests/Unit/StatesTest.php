<?php

declare(strict_types=1);

use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\States\Cancelled;
use Selli\Commerce\Order\States\Completed;
use Selli\Commerce\Order\States\Confirmed;
use Selli\Commerce\Order\States\PartiallyRefunded;
use Selli\Commerce\Order\States\Pending;
use Selli\Commerce\Order\States\Processing;
use Selli\Commerce\Order\States\Refunded;

beforeEach(function (): void {
    $this->order = Order::factory()->create();
});

it('exposes a human label for every state', function (): void {
    expect((new Pending($this->order))->label())->toBe('Pending')
        ->and((new Confirmed($this->order))->label())->toBe('Confirmed')
        ->and((new Processing($this->order))->label())->toBe('Processing')
        ->and((new Completed($this->order))->label())->toBe('Completed')
        ->and((new Cancelled($this->order))->label())->toBe('Cancelled')
        ->and((new Refunded($this->order))->label())->toBe('Refunded')
        ->and((new PartiallyRefunded($this->order))->label())->toBe('Partially refunded');
});

it('marks terminal states as final', function (): void {
    expect((new Cancelled($this->order))->isFinal())->toBeTrue()
        ->and((new Refunded($this->order))->isFinal())->toBeTrue()
        ->and((new Pending($this->order))->isFinal())->toBeFalse()
        ->and((new Completed($this->order))->isFinal())->toBeFalse()
        ->and((new PartiallyRefunded($this->order))->isFinal())->toBeFalse();
});

it('persists the default pending state via the factory', function (): void {
    expect($this->order->state)->toBeInstanceOf(Pending::class)
        ->and(Pending::$name)->toBe('pending');
});
