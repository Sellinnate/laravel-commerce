<?php

declare(strict_types=1);

use Brick\Math\RoundingMode;
use Selli\Commerce\Calculation\Calculators\GrandTotalCalculator;
use Selli\Commerce\Enums\MergeStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Default currency
    |--------------------------------------------------------------------------
    | ISO-4217 code used when a cart is created without an explicit currency.
    */
    'default_currency' => env('COMMERCE_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    | Prefix applied to every table so the engine coexists with the host
    | application's schema without collisions.
    */
    'table_prefix' => env('COMMERCE_TABLE_PREFIX', 'commerce_'),

    /*
    |--------------------------------------------------------------------------
    | Optional modules
    |--------------------------------------------------------------------------
    | Toggle each module independently. A disabled module registers no
    | calculators and leaves no dead code path in the pipeline.
    */
    'modules' => [
        'pricing' => env('COMMERCE_MODULE_PRICING', true),
        'tax' => env('COMMERCE_MODULE_TAX', true),
        'inventory' => env('COMMERCE_MODULE_INVENTORY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */
    'cart' => [
        // Storage driver. "database" is bundled (persistent, multi-device).
        // For session or cache/redis storage, implement the CartRepository
        // contract and bind it via the "bindings" map below.
        'driver' => env('COMMERCE_CART_DRIVER', 'database'),

        // Cache store used by a custom cache-backed CartRepository.
        'cache_store' => env('COMMERCE_CART_CACHE_STORE', null),

        // Minutes of inactivity before a cart is considered abandoned/expired.
        'ttl' => (int) env('COMMERCE_CART_TTL', 60 * 24 * 7),

        // How a guest cart merges into a user cart on login.
        'merge_strategy' => MergeStrategy::Sum,

        // Adding the same purchasable + options increments quantity instead of
        // creating a duplicate line.
        'idempotent_add' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Calculation pipeline
    |--------------------------------------------------------------------------
    | Ordered list of calculator classes resolved from the container. Modules
    | inject their calculators here; reorder freely. GrandTotalCalculator must
    | run last so rounding consolidates everything.
    */
    'pipeline' => [
        // Pricing, Tax and Inventory modules splice their calculators in here
        // via config publishing; the core guarantees the final consolidation.
        GrandTotalCalculator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rounding
    |--------------------------------------------------------------------------
    | A single rounding mode governs the whole engine. Per-currency scale is
    | handled automatically (JPY 0 decimals, BHD 3 decimals).
    */
    'rounding' => [
        'mode' => RoundingMode::HalfUp,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    | "null"    — single tenant (default), no scoping cost.
    | "callback"— resolve the tenant id from the callback below.
    | A custom TenantContext binding always wins over this setting.
    */
    'tenancy' => [
        'mode' => env('COMMERCE_TENANCY', 'null'),

        // Used when mode = "callback".
        'resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    | level 1 — immutable audit trail (always on): domain events + order state
    |           transitions are persisted append-only.
    | level 2 — opt-in event sourcing of the Order aggregate (requires
    |           spatie/laravel-event-sourcing).
    */
    'audit' => [
        'record_domain_events' => true,
        'event_sourcing' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Order
    |--------------------------------------------------------------------------
    */
    'order' => [
        // Prefix for the default sequential order number generator.
        'number_prefix' => env('COMMERCE_ORDER_PREFIX', 'ORD-'),
        'number_pad' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Contract bindings
    |--------------------------------------------------------------------------
    | Map any contract to a custom implementation. Empty = sensible defaults.
    | e.g. \Selli\Commerce\Contracts\TaxResolver::class => App\MyTaxResolver::class
    */
    'bindings' => [
        // Contract::class => Implementation::class
    ],
];
