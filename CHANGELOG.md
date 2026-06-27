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
- Full docmd documentation site and a Pest suite at 90%+ coverage, PHPStan max, Pint.
