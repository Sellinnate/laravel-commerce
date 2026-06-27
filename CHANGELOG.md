# Changelog

All notable changes to `selli/commerce` will be documented in this file.

## Unreleased

### Added
- Core domain: catalog-agnostic `Purchasable` contract with live binding + immutable order snapshot.
- Cart aggregate and `CartManager` (add, update, setQuantity, remove, clear, merge, calculate, recalculate) with typed domain exceptions and idempotent add.
- Order aggregate with a `spatie/laravel-model-states` state machine, `PlaceOrder` (transactional cart→order conversion) and `TransitionOrderState` (legality + policy + append-only logging + events).
- Deterministic, explainable calculation pipeline (`Calculation`, `CalculationLine`, `Adjustment`, `Pipeline`) with centralised per-currency rounding.
- `brick/money` everywhere via a two-column `MoneyCast`; never floats.
- Multi-tenancy: `TenantContext`, global `TenantScope`, automatic tenant stamping.
- Audit level 1: append-only domain-event trail and order state-transition log.
- ACL: default-permissive `OrderPolicy`, authorised transitions.
- **Pricing & Promotions module** (toggleable via `commerce.modules.pricing`):
  - Price books — per-currency, per-segment, time-bounded prices with quantity tiers, resolved by a `PriceBookResolver` that overrides the `PriceResolver`.
  - Coupons — percentage/fixed, usage limits (global + per-customer), minimum spend, validity and currency checks with distinct typed exceptions; `applyCoupon`/`removeCoupon` on the cart, redemption recorded append-only on placement.
  - Promotions — a condition→action rule engine (`cart_subtotal_min`, `item_quantity_min`, `has_purchasable` → `percentage_off`, `fixed_off`, `free_shipping`) with explicit stacking policies (exclusive / cumulative / best-of) and priority.
  - Gift cards — prepaid balance applied as a tender (never below zero), decremented under a row lock with an append-only ledger on placement.
  - Pipeline auto-composition from enabled modules; events `CouponApplied`, `CouponRejected`, `PromotionApplied`, `GiftCardRedeemed`.
- **Tax module** (toggleable via `commerce.modules.tax`):
  - Inclusive (tax derived from the gross, never added twice) and exclusive (tax added on top) handling driven by `prices_include_tax`.
  - Tax rates by category × jurisdiction (country, optional region with precedence, validity windows) in basis points, resolved by a `TableTaxResolver` (overridable `TaxResolver` contract, e.g. for an external provider).
  - Per-purchasable tax category via the optional `Taxable` contract; cart `setTaxContext()` for jurisdiction and B2B/exemption flags.
  - B2B intra-EU reverse charge and customer/product exemptions, each annotated with a reason on the order for fiscal justification.
  - Tax computed on the discounted base and frozen per order line.
- Full docmd documentation site and a Pest suite at 90%+ coverage, PHPStan max, Pint.
