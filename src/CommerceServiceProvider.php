<?php

declare(strict_types=1);

namespace Selli\Commerce;

use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Audit\Models\DomainEvent;
use Selli\Commerce\Audit\RecordDomainEvents;
use Selli\Commerce\Calculation\Pipeline;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Cart\Models\CartItem;
use Selli\Commerce\Cart\Repositories\DatabaseCartRepository;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\CartRepository;
use Selli\Commerce\Contracts\OrderNumberGenerator;
use Selli\Commerce\Contracts\OrderRepository;
use Selli\Commerce\Contracts\PriceResolver;
use Selli\Commerce\Contracts\PurchasableResolver;
use Selli\Commerce\Contracts\RoundingStrategy;
use Selli\Commerce\Contracts\TenantContext;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\Models\OrderLine;
use Selli\Commerce\Order\Models\OrderStateTransition;
use Selli\Commerce\Order\Policies\OrderPolicy;
use Selli\Commerce\Order\Repositories\EloquentOrderRepository;
use Selli\Commerce\Order\Support\SequentialOrderNumberGenerator;
use Selli\Commerce\Support\DefaultPriceResolver;
use Selli\Commerce\Support\DefaultRoundingStrategy;
use Selli\Commerce\Support\EloquentPurchasableResolver;
use Selli\Commerce\Tenancy\CallbackTenantContext;
use Selli\Commerce\Tenancy\NullTenantContext;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CommerceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('commerce')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->bindTenantContext();
        $this->bindRounding();
        $this->bindContracts();
        $this->bindPipeline();
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'commerce-migrations');

        Relation::morphMap([
            'commerce.cart' => Cart::class,
            'commerce.cart_item' => CartItem::class,
            'commerce.order' => Order::class,
            'commerce.order_line' => OrderLine::class,
            'commerce.order_state_transition' => OrderStateTransition::class,
            'commerce.domain_event' => DomainEvent::class,
        ]);

        Gate::policy(Order::class, OrderPolicy::class);

        Event::listen('*', function (string $eventName, array $data): void {
            $event = $data[0] ?? null;

            if ($event instanceof Recordable) {
                $this->app->make(RecordDomainEvents::class)->handle($event);
            }
        });
    }

    private function bindTenantContext(): void
    {
        $this->app->singleton(TenantContext::class, function (): TenantContext {
            $override = $this->binding(TenantContext::class);

            if ($override !== null) {
                /** @var TenantContext */
                return $this->app->make($override);
            }

            if (config('commerce.tenancy.mode') === 'callback') {
                $resolver = config('commerce.tenancy.resolver');

                if (is_callable($resolver)) {
                    return new CallbackTenantContext(Closure::fromCallable($resolver));
                }
            }

            return new NullTenantContext;
        });
    }

    private function bindRounding(): void
    {
        $this->app->singleton(RoundingStrategy::class, function (): RoundingStrategy {
            $override = $this->binding(RoundingStrategy::class);

            if ($override !== null) {
                /** @var RoundingStrategy */
                return $this->app->make($override);
            }

            $mode = config('commerce.rounding.mode', RoundingMode::HalfUp);

            return new DefaultRoundingStrategy($mode instanceof RoundingMode ? $mode : RoundingMode::HalfUp);
        });
    }

    private function bindContracts(): void
    {
        $this->bindContract(PurchasableResolver::class, EloquentPurchasableResolver::class);
        $this->bindContract(PriceResolver::class, DefaultPriceResolver::class);
        $this->bindContract(OrderRepository::class, EloquentOrderRepository::class);
        $this->bindContract(OrderNumberGenerator::class, SequentialOrderNumberGenerator::class);

        $this->app->bind(CartRepository::class, function (): CartRepository {
            $override = $this->binding(CartRepository::class);

            if ($override !== null) {
                /** @var CartRepository */
                return $this->app->make($override);
            }

            return match (config('commerce.cart.driver', 'database')) {
                default => $this->app->make(DatabaseCartRepository::class),
            };
        });
    }

    private function bindPipeline(): void
    {
        $this->app->bind(Pipeline::class, function (): Pipeline {
            /** @var list<Calculator> $calculators */
            $calculators = [];

            /** @var array<int, class-string> $classes */
            $classes = (array) config('commerce.pipeline', []);

            foreach ($classes as $class) {
                /** @var Calculator $calculator */
                $calculator = $this->app->make($class);
                $calculators[] = $calculator;
            }

            return new Pipeline($calculators);
        });
    }

    /**
     * Bind a contract to its config override or the supplied default.
     *
     * @param  class-string  $contract
     * @param  class-string  $default
     */
    private function bindContract(string $contract, string $default): void
    {
        $this->app->bind($contract, $this->binding($contract) ?? $default);
    }

    /**
     * @param  class-string  $contract
     * @return class-string|null
     */
    private function binding(string $contract): ?string
    {
        $bindings = (array) config('commerce.bindings', []);

        $impl = $bindings[$contract] ?? null;

        /** @var class-string|null */
        return is_string($impl) && $impl !== '' ? $impl : null;
    }
}
