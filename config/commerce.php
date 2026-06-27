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
    | Leave empty to auto-compose the pipeline from the enabled modules
    | (Pricing → Tax → … → GrandTotal). Set a non-empty ordered list of
    | calculator class names to take full manual control; GrandTotalCalculator
    | must run last so rounding consolidates everything.
    */
    'pipeline' => [
        // GrandTotalCalculator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra calculators
    |--------------------------------------------------------------------------
    | Calculator classes appended to the auto-composed pipeline, just before
    | GrandTotalCalculator. The seam for project-specific calculators (loyalty
    | discount, eco-fee, …) without taking over the whole pipeline.
    */
    'pipeline_append' => [
        // App\Commerce\LoyaltyDiscountCalculator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing & Promotions module
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        // Default customer segment used when none is supplied in the context.
        'default_segment' => env('COMMERCE_DEFAULT_SEGMENT', 'default'),

        // Stacking policy applied to a Promotion created without an explicit
        // one: "exclusive" (best single), "cumulative" (all apply), "best_of".
        'stacking' => env('COMMERCE_PROMO_STACKING', 'cumulative'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax module
    |--------------------------------------------------------------------------
    */
    'tax' => [
        // Whether catalogue prices already include tax (B2C EU style) or are
        // net and tax is added on top (B2B / US style). Inclusive tax is
        // reported but never added twice to the total.
        'prices_include_tax' => (bool) env('COMMERCE_TAX_INCLUSIVE', true),

        // Tax category used for a purchasable that does not declare one.
        'default_category' => env('COMMERCE_TAX_CATEGORY', 'standard'),

        // Allow the B2B intra-EU reverse charge (VAT not applied, annotated)
        // when the cart's tax context marks the customer as a VAT-registered
        // business in a different EU country.
        'reverse_charge' => (bool) env('COMMERCE_TAX_REVERSE_CHARGE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory module
    |--------------------------------------------------------------------------
    | Stock is tracked per purchasable × warehouse. Available-to-promise is
    | on_hand − active reservations; that is the number isAvailable() consults.
    */
    'inventory' => [
        // Code of the warehouse used when a single-warehouse app does not pick
        // one explicitly. Auto-created on first use.
        'default_warehouse' => env('COMMERCE_INVENTORY_WAREHOUSE', 'default'),

        // When stock is reserved: "place_order" (only at checkout, the default
        // and cheapest) or "add_to_cart" (held with a TTL the moment a line is
        // added, released when removed or when the TTL lapses).
        'reserve_on' => env('COMMERCE_INVENTORY_RESERVE_ON', 'place_order'),

        // Minutes a cart reservation is held before it is considered expired and
        // its stock is promised to someone else again. Null disables expiry.
        'reservation_ttl' => env('COMMERCE_INVENTORY_RESERVATION_TTL', 60),

        // Whether a purchase may exceed available stock. "deny" throws
        // InsufficientStockException; "allow" lets the line through and the
        // order is annotated as a backorder (emitting BackorderCreated).
        'backorder' => env('COMMERCE_INVENTORY_BACKORDER', 'deny'),
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
