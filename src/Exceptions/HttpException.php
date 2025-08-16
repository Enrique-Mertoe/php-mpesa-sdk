<?php

/**
 * M-Pesa SDK HTTP Exception
 *
 * Exception thrown for HTTP-related errors during API communication.
 *
 * @package MpesaSDK\Exceptions
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Exceptions;

class HttpException extends MpesaException
{
    protected $responseBody;

    public function __construct(
        string $message = '',
        int $code = 0,
        \Exception $previous = null,
        string $mpesaErrorCode = '',
        string $responseBody = ''
    ) {
        parent::__construct($message, $code, $previous, $mpesaErrorCode, $responseBody);
        $this->responseBody = $responseBody;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}