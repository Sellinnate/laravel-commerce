<?php

declare(strict_types=1);

namespace Selli\Commerce\Exceptions;

use RuntimeException;

/**
 * Root of every typed domain exception. Domain errors are explicit, typed
 * exceptions — never silent `false` returns.
 */
abstract class CommerceException extends RuntimeException {}
