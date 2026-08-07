<?php

namespace Azera\Log;

use Psr\Log\LoggerInterface;

/**
 * No-op logger that discards every message.
 *
 * This is the default logger returned by {@see \Azera\AppContext::logger()}
 * when no concrete logger has been registered. It exists so that calling
 * code can safely invoke `$ctx->logger()->info(...)` without null-checks
 * or errors, paying only the cost of a single method call that does
 * nothing.
 *
 * Implements the PSR-3 {@see LoggerInterface}, so it is interchangeable
 * with any PSR-3 logger (e.g. Monolog). Register a real logger via
 * `AppContext::set(LoggerInterface::class, $logger)`.
 */
final class NullLogger implements LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void {}
    public function alert(string|\Stringable $message, array $context = []): void {}
    public function critical(string|\Stringable $message, array $context = []): void {}
    public function error(string|\Stringable $message, array $context = []): void {}
    public function warning(string|\Stringable $message, array $context = []): void {}
    public function notice(string|\Stringable $message, array $context = []): void {}
    public function info(string|\Stringable $message, array $context = []): void {}
    public function debug(string|\Stringable $message, array $context = []): void {}
    public function log($level, string|\Stringable $message, array $context = []): void {}
}