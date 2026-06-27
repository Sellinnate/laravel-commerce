<?php

declare(strict_types=1);

namespace Selli\Commerce;

use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Selli\Commerce\Audit\Contracts\Recordable;
use Selli\Commerce\Audit\Models\DomainEvent;
use Selli\Commerce\Audit\RecordDomainEvents;
use Selli\Commerce\Calculation\Calculators\GrandTotalCalculator;
use Selli\Commerce\Calculation\Pipeline;
use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Cart\Models\CartItem;
use Selli\Commerce\Cart\Repositories\DatabaseCartRepository;
use Selli\Commerce\Contracts\Calculator;
use Selli\Commerce\Contracts\CartRepository;
use Selli\Commerce\Contracts\CouponValidator;
use Selli\Commerce\Contracts\GiftCardValidator;
use Selli\Commerce\Contracts\OrderNumberGenerator;
use Selli\Commerce\Contracts\OrderRepository;
use Selli\Commerce\Contracts\PriceResolver;
use Selli\Commerce\Contracts\PurchasableResolver;
use Selli\Commerce\Contracts\RoundingStrategy;
use Selli\Commerce\Contracts\TaxResolver;
use Selli\Commerce\Contracts\TenantContext;
use Selli\Commerce\Events\Order\OrderPlaced;
use Selli\Commerce\Order\Models\Order;
use Selli\Commerce\Order\Models\OrderLine;
use Selli\Commerce\Order\Models\OrderStateTransition;
use Selli\Commerce\Order\Policies\OrderPolicy;
use Selli\Commerce\Order\Repositories\EloquentOrderRepository;
use Selli\Commerce\Order\Support\SequentialOrderNumberGenerator;
use Selli\Commerce\Pricing\Calculators\CouponDiscountCalculator;
use Selli\Commerce\Pricing\Calculators\GiftCardCalculator;
use Selli\Commerce\Pricing\Calculators\PromotionCalculator;
use Selli\Commerce\Pricing\DatabaseCouponValidator;
use Selli\Commerce\Pricing\DatabaseGiftCardValidator;
use Selli\Commerce\Pricing\Listeners\RecordPricingUsage;
use Selli\Commerce\Pricing\Models\Coupon;
use Selli\Commerce\Pricing\Models\CouponRedemption;
use Selli\Commerce\Pricing\Models\GiftCard;
use Selli\Commerce\Pricing\Models\GiftCardTransaction;
use Selli\Commerce\Pricing\Models\Price;
use Selli\Commerce\Pricing\Models\PriceBook;
use Selli\Commerce\Pricing\Models\Promotion;
use Selli\Commerce\Pricing\NullCouponValidator;
use Selli\Commerce\Pricing\NullGiftCardValidator;
use Selli\Commerce\Pricing\PriceBookResolver;
use Selli\Commerce\Support\DefaultPriceResolver;
use Selli\Commerce\Support\DefaultRoundingStrategy;
use Selli\Commerce\Support\EloquentPurchasableResolver;
use Selli\Commerce\Tax\Calculators\TaxCalculator;
use Selli\Commerce\Tax\Models\TaxRate;
use Selli\Commerce\Tax\NullTaxResolver;
use Selli\Commerce\Tax\TableTaxResolver;
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
            'commerce.price_book' => PriceBook::class,
            'commerce.price' => Price::class,
            'commerce.coupon' => Coupon::class,
            'commerce.coupon_redemption' => CouponRedemption::class,
            'commerce.promotion' => Promotion::class,
            'commerce.gift_card' => GiftCard::class,
            'commerce.gift_card_transaction' => GiftCardTransaction::class,
            'commerce.tax_rate' => TaxRate::class,
        ]);

        Gate::policy(Order::class, OrderPolicy::class);

        Event::listen('*', function (string $eventName, array $data): void {
            $event = $data[0] ?? null;

            if ($event instanceof Recordable) {
                $this->app->make(RecordDomainEvents::class)->handle($event);
            }
        });

        if ($this->pricingEnabled()) {
            Event::listen(OrderPlaced::class, [RecordPricingUsage::class, 'handle']);
        }
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
        $this->bindContract(OrderRepository::class, EloquentOrderRepository::class);
        $this->bindContract(OrderNumberGenerator::class, SequentialOrderNumberGenerator::class);

        $this->app->bind(PriceResolver::class, function (): PriceResolver {
            $override = $this->binding(PriceResolver::class);

            if ($override !== null) {
                /** @var PriceResolver */
                return $this->app->make($override);
            }

            if ($this->pricingEnabled()) {
                return new PriceBookResolver($this->app->make(DefaultPriceResolver::class));
            }

            return $this->app->make(DefaultPriceResolver::class);
        });

        $this->app->bind(CouponValidator::class, function (): CouponValidator {
            $override = $this->binding(CouponValidator::class);

            if ($override !== null) {
                /** @var CouponValidator */
                return $this->app->make($override);
            }

            return $this->pricingEnabled()
                ? $this->app->make(DatabaseCouponValidator::class)
                : $this->app->make(NullCouponValidator::class);
        });

        $this->app->bind(GiftCardValidator::class, function (): GiftCardValidator {
            $override = $this->binding(GiftCardValidator::class);

            if ($override !== null) {
                /** @var GiftCardValidator */
                return $this->app->make($override);
            }

            return $this->pricingEnabled()
                ? $this->app->make(DatabaseGiftCardValidator::class)
                : $this->app->make(NullGiftCardValidator::class);
        });

        $this->app->bind(TaxResolver::class, function (): TaxResolver {
            $override = $this->binding(TaxResolver::class);

            if ($override !== null) {
                /** @var TaxResolver */
                return $this->app->make($override);
            }

            return $this->taxEnabled()
                ? $this->app->make(TableTaxResolver::class)
                : $this->app->make(NullTaxResolver::class);
        });

        $this->app->bind(CartRepository::class, function (): CartRepository {
            $override = $this->binding(CartRepository::class);

            if ($override !== null) {
                /** @var CartRepository */
                return $this->app->make($override);
            }

            $driver = Config::string('commerce.cart.driver', 'database');

            return match ($driver) {
                'database' => $this->app->make(DatabaseCartRepository::class),
                default => throw new InvalidArgumentException(
                    "Unsupported cart driver [{$driver}]. The \"database\" driver is bundled; ".
                    'for session or cache storage bind a custom CartRepository via commerce.bindings.'
                ),
            };
        });
    }

    private function bindPipeline(): void
    {
        $this->app->bind(Pipeline::class, function (): Pipeline {
            /** @var list<Calculator> $calculators */
            $calculators = [];

            /** @var array<int, class-string> $configured */
            $configured = (array) config('commerce.pipeline', []);
            $classes = $configured !== [] ? $configured : $this->defaultPipeline();

            foreach ($classes as $class) {
                /** @var Calculator $calculator */
                $calculator = $this->app->make($class);
                $calculators[] = $calculator;
            }

            return new Pipeline($calculators);
        });
    }

    private function pricingEnabled(): bool
    {
        return Config::boolean('commerce.modules.pricing', true);
    }

    private function taxEnabled(): bool
    {
        return Config::boolean('commerce.modules.tax', true);
    }

    /**
     * Auto-compose the pipeline from the enabled modules, ending with the
     * mandatory GrandTotalCalculator.
     *
     * @return list<class-string<Calculator>>
     */
    private function defaultPipeline(): array
    {
        $classes = [];

        if ($this->pricingEnabled()) {
            $classes[] = PromotionCalculator::class;
            $classes[] = CouponDiscountCalculator::class;
        }

        if ($this->taxEnabled()) {
            $classes[] = TaxCalculator::class;
        }

        if ($this->pricingEnabled()) {
            $classes[] = GiftCardCalculator::class;
        }

        foreach ((array) config('commerce.pipeline_append', []) as $class) {
            if (is_string($class) && is_a($class, Calculator::class, true)) {
                $classes[] = $class;
            }
        }

        $classes[] = GrandTotalCalculator::class;

        return $classes;
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
