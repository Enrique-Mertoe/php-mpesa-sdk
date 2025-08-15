<?php

/**
 * M-Pesa SDK HTTP Response Handler
 * 
 * Handles HTTP responses from M-Pesa API endpoints.
 * Provides methods for parsing and validating response data.
 * 
 * @package MpesaSDK\Http
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Http;

use MpesaSDK\Exceptions\HttpException;

class Response
{
    private $statusCode;
    private $body;
    private $decodedBody;

    public function __construct(int $statusCode, string $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->decodedBody = $this->parseBody();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDecodedBody(): ?array
    {
        return $this->decodedBody;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    public function hasError(): bool
    {
        return !$this->isSuccessful();
    }

    public function get(string $key, $default = null)
    {
        if ($this->decodedBody === null) {
            return $default;
        }

        return $this->decodedBody[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return $this->decodedBody !== null && array_key_exists($key, $this->decodedBody);
    }

    public function getData(): ?array
    {
        return $this->decodedBody;
    }

    public function getErrorMessage(): string
    {
        if ($this->isSuccessful()) {
            return '';
        }

        // Try to extract error message from various possible fields
        $errorFields = ['errorMessage', 'error', 'message', 'Error', 'errorCode'];

        foreach ($errorFields as $field) {
            if ($this->has($field)) {
                return $this->get($field);
            }
        }

        // If no specific error message found, return generic message
        return "HTTP {$this->statusCode}: " . $this->getStatusText();
    }

    public function getErrorCode(): string
    {
        if ($this->isSuccessful()) {
            return '';
        }

        $codeFields = ['errorCode', 'code', 'Code'];

        foreach ($codeFields as $field) {
            if ($this->has($field)) {
                return (string) $this->get($field);
            }
        }

        return (string) $this->statusCode;
    }

    public function throwIfError(): self
    {
        if ($this->hasError()) {
            throw new HttpException(
                $this->getErrorMessage(),
                $this->statusCode,
                null,
                $this->getErrorCode(),
                $this->body
            );
        }

        return $this;
    }

    private function parseBody(): ?array
    {
        if (empty($this->body)) {
            return null;
        }

        $decoded = json_decode($this->body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    private function getStatusText(): string
    {
        $statusTexts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout'
        ];

        return $statusTexts[$this->statusCode] ?? 'Unknown Status';
    }

    public function toArray(): array
    {
        return [
            'status_code' => $this->statusCode,
            'body' => $this->body,
            'data' => $this->decodedBody,
            'is_successful' => $this->isSuccessful(),
            'error_message' => $this->getErrorMessage(),
            'error_code' => $this->getErrorCode()
        ];
    }

    public function __toString(): string
    {
        return $this->body;
    }
}