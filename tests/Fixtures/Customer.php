<?php

declare(strict_types=1);

namespace Selli\Commerce\Tests\Fixtures;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * A minimal authorizable customer/actor fixture for cart ownership and order
 * actor attribution in tests.
 *
 * @property string $id
 * @property string|null $name
 */
class Customer extends Model implements AuthorizableContract
{
    use Authorizable;
    use HasUlids;

    protected $table = 'customers';

    protected $guarded = [];
}
