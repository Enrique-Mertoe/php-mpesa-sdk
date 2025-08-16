<?php

/**
 * M-Pesa SDK Authentication Exception
 *
 * Exception thrown for authentication and authorization errors.
 *
 * @package MpesaSDK\Exceptions
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Exceptions;

class AuthException extends MpesaException
{
    public static function invalidCredentials(): self
    {
        return new self('Invalid M-Pesa API credentials provided');
    }

    public static function tokenExpired(): self
    {
        return new self('M-Pesa access token has expired');
    }

    public static function tokenGenerationFailed(string $reason = ''): self
    {
        $message = 'Failed to generate M-Pesa access token';
        if ($reason) {
            $message .= ': ' . $reason;
        }
        return new self($message);
    }
}