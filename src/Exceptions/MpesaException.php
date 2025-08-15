<?php

/**
 * M-Pesa SDK Exception Classes
 * 
 * Custom exception classes for handling M-Pesa API errors and SDK-specific exceptions.
 * Provides detailed error information including M-Pesa error codes and messages.
 * 
 * @package MpesaSDK\Exceptions
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Exceptions;

use Exception;

class MpesaException extends Exception
{
    protected $mpesaErrorCode;
    protected $mpesaErrorMessage;

    public function __construct(
        string $message = '',
        int $code = 0,
        Exception $previous = null,
        string $mpesaErrorCode = '',
        string $mpesaErrorMessage = ''
    ) {
        parent::__construct($message, $code, $previous);

        $this->mpesaErrorCode = $mpesaErrorCode;
        $this->mpesaErrorMessage = $mpesaErrorMessage;
    }

    public function getMpesaErrorCode(): string
    {
        return $this->mpesaErrorCode;
    }

    public function getMpesaErrorMessage(): string
    {
        return $this->mpesaErrorMessage;
    }

    public function getFullErrorMessage(): string
    {
        $message = $this->getMessage();

        if (!empty($this->mpesaErrorCode)) {
            $message .= " (M-Pesa Error: {$this->mpesaErrorCode})";
        }

        if (!empty($this->mpesaErrorMessage)) {
            $message .= " - {$this->mpesaErrorMessage}";
        }

        return $message;
    }
}

// Specific exception classes
class AuthException extends MpesaException {}

class ValidationException extends MpesaException {}

class HttpException extends MpesaException {}

class ConfigException extends MpesaException {}