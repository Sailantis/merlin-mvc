<?php

namespace Azera\Validation;

/**
 * Thrown by Validator::validate() when one or more field rules fail.
 *
 * The errors array is keyed by dot-path field name (e.g. "address.zip", "tags[0]")
 * and each value is a human-readable error message string.
 */
class ValidationException extends \Azera\Exception
{
    /**
     * @param array<string, string> $errors Dot-path field errors.
     */
    public function __construct(private array $errors)
    {
        parent::__construct('Validation failed: ' . implode(', ', array_keys($errors)));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
