<?php

namespace MpesaSDK\Callbacks;

use MpesaSDK\Utils\Logger;

abstract class CallbackHandler
{
    protected $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Get callback data from HTTP request
     */
    protected function getCallbackData(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON in callback data');
        }

        if (empty($data)) {
            throw new \InvalidArgumentException('Empty callback data received');
        }

        return $data;
    }

    /**
     * Log message with optional context
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Send JSON response
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * Abstract method to process callback
     */
    abstract public function process(): array;
}