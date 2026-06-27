<?php

declare(strict_types=1);

namespace Selli\Commerce\Cart\Repositories;

use Selli\Commerce\Cart\Models\Cart;
use Selli\Commerce\Contracts\CartRepository;
use Selli\Commerce\Enums\CartStatus;

/**
 * Persistent, multi-device cart storage (default driver). Survives the
 * session and underpins cart recovery and analytics. Tenant scoping is applied
 * transparently by the model's global scope.
 */
final class DatabaseCartRepository implements CartRepository
{
    public function find(string $id): ?Cart
    {
        return Cart::query()->with('items')->find($id);
    }

    public function findActiveForOwner(string $ownerType, string $ownerId): ?Cart
    {
        return Cart::query()
            ->with('items')
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('status', CartStatus::Active->value)
            ->where(function ($query): void {
                // Never resurrect a cart whose TTL has already elapsed.
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();
    }

    public function save(Cart $cart): Cart
    {
        $cart->save();

        return $cart;
    }

    public function delete(Cart $cart): void
    {
        $cart->delete();
    }
}
