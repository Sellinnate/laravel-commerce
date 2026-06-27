<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

final class ImmutableRecordException extends CommerceException
{
    public static function cannotModify(string $model): self
    {
        return new self("{$model} records are append-only and cannot be updated or deleted.");
    }
}
