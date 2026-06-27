<?php

declare(strict_types=1);

namespace Selli\Commerce\Order\States;

use Selli\Commerce\Order\Models\Order;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * The order lifecycle as a state machine. Illegal transitions are impossible
 * by construction, not by `if`. Each transition is authorised, logged and
 * emits a domain event (handled by the application layer).
 *
 *   pending → confirmed → processing → completed
 *      │          │            │
 *      └───────► cancelled ◄───┘
 *                    │
 *               (completed) → refunded / partially_refunded
 *
 * @extends State<Order>
 */
abstract class OrderState extends State
{
    /** Morph name of the concrete state, declared by each subclass. */
    public static string $name;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Confirmed::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Confirmed::class, Processing::class)
            ->allowTransition(Confirmed::class, Cancelled::class)
            ->allowTransition(Processing::class, Completed::class)
            ->allowTransition(Processing::class, Cancelled::class)
            ->allowTransition(Completed::class, Refunded::class)
            ->allowTransition(Completed::class, PartiallyRefunded::class)
            ->allowTransition(PartiallyRefunded::class, Refunded::class);
    }

    abstract public function label(): string;

    /**
     * Whether the order is in a terminal state (no further transitions).
     */
    public function isFinal(): bool
    {
        return false;
    }
}
