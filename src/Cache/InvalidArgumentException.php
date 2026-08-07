<?php

namespace Azera\Cache;

/**
 * Exception thrown by cache implementations on invalid keys or arguments.
 *
 * Implements the PSR-16 {@see \Psr\SimpleCache\InvalidArgumentException}
 * interface, so it satisfies catch blocks that type-hint the PSR-16
 * exception interface.
 */
class InvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{
}