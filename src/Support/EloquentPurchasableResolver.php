<?php

declare(strict_types=1);

namespace Selli\Commerce\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Selli\Commerce\Contracts\Purchasable;
use Selli\Commerce\Contracts\PurchasableResolver;

/**
 * Default resolver: maps the stored morph type back to its Eloquent model and
 * loads it by key. Returns null when the catalogue row no longer exists.
 */
final class EloquentPurchasableResolver implements PurchasableResolver
{
    public function resolve(string $type, string $id): ?Purchasable
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class) || ! is_a($class, Model::class, true)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $class;

        $model = $instance->newQuery()->whereKey($id)->first();

        return $model instanceof Purchasable ? $model : null;
    }
}
