<?php

declare(strict_types=1);

namespace Selli\Commerce\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * Resolves the model's table name by prefixing {@see $baseTable} with the
 * configurable `commerce.table_prefix`, so the engine coexists with the host
 * schema without collisions.
 *
 * @property string $baseTable
 */
trait HasPrefixedTable
{
    public function getTable(): string
    {
        if (isset($this->table)) {
            /** @var string $table */
            $table = $this->table;

            return $table;
        }

        return Config::string('commerce.table_prefix', 'commerce_').$this->baseTable;
    }
}
