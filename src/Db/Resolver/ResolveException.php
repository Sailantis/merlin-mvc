<?php

declare(strict_types=1);

namespace Azera\Db\Resolver;

use RuntimeException;

/**
 * Thrown when a {@see TableResolver} cannot resolve a logical name
 * to a concrete table source.
 */
class ResolveException extends RuntimeException
{
}