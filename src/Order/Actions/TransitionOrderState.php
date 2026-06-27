<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Events\Order\OrderCancelled;
use Selli\Commerce\Events\Order\OrderCompleted;
use Selli\Commerce\Events\Order\OrderConfirmed;
use Selli\Commerce\Events\Order\OrderProcessing;
use Selli\Commerce\Events\Order\OrderRefunded;
use Selli\Commerce\Events\Order\OrderStateTransitioned;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\Models\OrderStateTransition;
use Selli\Commerce\Order\States\Cancelled;
use Selli\Commerce\Order\States\Completed;
use Selli\Commerce\Order\States\Confirmed;
use Selli\Commerce\Order\States\OrderState;
use Selli\Commerce\Order\States\PartiallyRefunded;
use Selli\Commerce\Order\States\Processing;
use Selli\Commerce\Order\States\Refunded;

/**
 * Transitions an order through the state machine. Enforces both the *legality*
 * of the transition (spatie/laravel-model-states) and the *permission* of the
 * actor (policy), then logs the change append-only and emits events.
 */
final class TransitionOrderState
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly Gate $gate,
    ) {}

    /**
     * @param  class-string<OrderState>  $toState
     */
    public function handle(Order $order, string $toState, ?Model $by = null, ?string $reason = null): Order
    {
        if ($by instanceof Authorizable) {
            $this->gate->forUser($by)->authorize('transition', [$order, $toState]);
        }

        $from = $order->state::$name;

        $actorId = null;
        if ($by !== null) {
            /** @var int|string $key */
            $key = $by->getKey();
            $actorId = (string) $key;
        }

        $order->state->transitionTo($toState);
        $order->refresh();

        $to = $order->state::$name;

        OrderStateTransition::query()->create([
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'from_state' => $from,
            'to_state' => $to,
            'actor_type' => $by?->getMorphClass(),
            'actor_id' => $actorId,
            'reason' => $reason,
        ]);

        $this->events->dispatch(new OrderStateTransitioned(
            $order,
            $from,
            $to,
            $by?->getMorphClass(),
            $actorId,
            $reason,
        ));

        $this->dispatchSpecific($order, $toState);

        return $order;
    }

    /**
     * @param  class-string<OrderState>  $toState
     */
    private function dispatchSpecific(Order $order, string $toState): void
    {
        $event = match ($toState) {
            Confirmed::class => new OrderConfirmed($order),
            Processing::class => new OrderProcessing($order),
            Completed::class => new OrderCompleted($order),
            Cancelled::class => new OrderCancelled($order),
            Refunded::class, PartiallyRefunded::class => new OrderRefunded($order),
            default => null,
        };

        if ($event !== null) {
            $this->events->dispatch($event);
        }
    }
}
