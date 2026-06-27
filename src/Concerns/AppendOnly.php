<?php

declare(strict_types=1);

namespace Selli\Commerce\Concerns;

use Illuminate\Database\Eloquent\Model;
use Selli\Commerce\Exceptions\ImmutableRecordException;

/**
 * Enforces append-only semantics at the model layer: once written, a record can
 * never be updated or deleted. Used for the audit trail and the order state
 * transition log, where history must be tamper-evident.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function (Model $model): void {
            throw ImmutableRecordException::cannotModify(class_basename($model));
        });

        static::deleting(function (Model $model): void {
            throw ImmutableRecordException::cannotModify(class_basename($model));
        });
    }
}
