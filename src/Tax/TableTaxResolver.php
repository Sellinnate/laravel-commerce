<?php

declare(strict_types=1);

namespace Selli\Commerce\Tax;

use Selli\Commerce\Contracts\TaxResolver;
use Selli\Commerce\Tax\Models\TaxRate;

/**
 * Default resolver: looks up the effective rate in the `tax_rates` table for the
 * category and jurisdiction. A region-specific rate beats a country-wide one,
 * then the higher priority wins. Scoped explicitly to the jurisdiction tenant
 * (not the ambient tenant context).
 */
final class TableTaxResolver implements TaxResolver
{
    public function resolve(string $category, array $jurisdiction): ?RateResult
    {
        $country = $jurisdiction['country'] ?? null;

        if (! is_string($country) || $country === '') {
            return null;
        }

        $region = is_string($jurisdiction['region'] ?? null) ? $jurisdiction['region'] : null;
        $tenantId = is_string($jurisdiction['tenant_id'] ?? null) ? $jurisdiction['tenant_id'] : null;

        $rates = TaxRate::withoutTenantScope()
            ->valid(now())
            ->where('category', $category)
            ->where('country', $country)
            ->when(
                $tenantId === null,
                fn ($query) => $query->whereNull('tenant_id'),
                fn ($query) => $query->where('tenant_id', $tenantId),
            )
            ->where(fn ($query) => $query->whereNull('region')->orWhere('region', $region))
            // Deterministic tie-break for equally specific, equal-priority rows:
            // order by the unique, monotonic ULID key so the newest rate wins.
            // PHP's sort below is stable, so this ordering survives the re-sort.
            ->orderByDesc('id')
            ->get();

        $best = $rates->sort(function (TaxRate $a, TaxRate $b) use ($region): int {
            $regionalA = $region !== null && $a->region === $region ? 1 : 0;
            $regionalB = $region !== null && $b->region === $region ? 1 : 0;

            if ($regionalA !== $regionalB) {
                return $regionalB <=> $regionalA;
            }

            return $b->priority <=> $a->priority;
        })->first();

        if (! $best instanceof TaxRate) {
            return null;
        }

        return new RateResult($best->rate, $best->name);
    }
}
