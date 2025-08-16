<?php

/**
 * M-Pesa SDK Validation Exception
 *
 * Exception thrown for validation errors in input data.
 *
 * @package MpesaSDK\Exceptions
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Exceptions;

class ValidationException extends MpesaException
{
    protected $validationErrors = [];

    public function __construct(
        string $message = '',
        array $validationErrors = [],
        int $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $validationErrors;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public static function forField(string $field, string $error): self
    {
        return new self(
            "Validation failed for field '{$field}': {$error}",
            [$field => $error]
        );
    }

    public static function multiple(array $errors): self
    {
        $message = 'Multiple validation errors occurred: ' . implode(', ', array_keys($errors));
        return new self($message, $errors);
    }
}