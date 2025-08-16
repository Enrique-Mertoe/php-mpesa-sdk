<?php

/**
 * M-Pesa STK Push Callback Handler
 * 
 * Handles STK Push callback responses from M-Pesa API.
 * Provides methods to process and validate callback data.
 * 
 * @package MpesaSDK\Callbacks
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Callbacks;

use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\ValidationException;

class STKPushCallback
{
    private ?Logger $logger;
    private $onSuccessCallback = null;
    private $onFailureCallback = null;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Set success callback function
     */
    public function onSuccess(callable $callback): self
    {
        $this->onSuccessCallback = $callback;
        return $this;
    }

    /**
     * Set failure callback function
     */
    public function onFailure(callable $callback): self
    {
        $this->onFailureCallback = $callback;
        return $this;
    }

    /**
     * Process STK Push callback
     */
    public function process(?array $callbackData = null): array
    {
        // Get callback data from request if not provided
        if ($callbackData === null) {
            $callbackData = $this->getCallbackData();
        }

        // Validate and parse callback data
        $parsedData = $this->parseCallback($callbackData);

        // Log callback received
        if ($this->logger) {
            $this->logger->info('STK Push callback received', [
                'result_code' => $parsedData['result_code'],
                'checkout_request_id' => $parsedData['checkout_request_id']
            ]);
        }

        // Execute appropriate callback
        if ($parsedData['is_successful']) {
            if ($this->onSuccessCallback) {
                call_user_func($this->onSuccessCallback, $parsedData);
            }
            
            if ($this->logger) {
                $this->logger->info('STK Push transaction successful', [
                    'mpesa_receipt_number' => $parsedData['mpesa_receipt_number'],
                    'amount' => $parsedData['amount']
                ]);
            }
        } else {
            if ($this->onFailureCallback) {
                call_user_func($this->onFailureCallback, $parsedData);
            }
            
            if ($this->logger) {
                $this->logger->error('STK Push transaction failed', [
                    'result_desc' => $parsedData['result_desc'],
                    'result_code' => $parsedData['result_code']
                ]);
            }
        }

        // Return response for M-Pesa
        return [
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ];
    }

    /**
     * Get callback data from HTTP request
     */
    private function getCallbackData(): array
    {
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            throw new ValidationException('No callback data received');
        }

        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException('Invalid JSON in callback data: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Parse and validate STK Push callback data
     */
    public function parseCallback(array $callbackData): array
    {
        if (!isset($callbackData['Body']['stkCallback'])) {
            throw new ValidationException('Invalid STK Push callback structure');
        }

        $callback = $callbackData['Body']['stkCallback'];

        $parsed = [
            'merchant_request_id' => $callback['MerchantRequestID'] ?? '',
            'checkout_request_id' => $callback['CheckoutRequestID'] ?? '',
            'result_code' => (int)($callback['ResultCode'] ?? 1),
            'result_desc' => $callback['ResultDesc'] ?? '',
            'amount' => null,
            'mpesa_receipt_number' => null,
            'transaction_date' => null,
            'phone_number' => null,
            'is_successful' => ((int)($callback['ResultCode'] ?? 1)) === 0
        ];

        // Extract metadata if transaction was successful
        if ($parsed['is_successful'] && isset($callback['CallbackMetadata']['Item'])) {
            foreach ($callback['CallbackMetadata']['Item'] as $item) {
                switch ($item['Name']) {
                    case 'Amount':
                        $parsed['amount'] = $item['Value'] ?? null;
                        break;
                    case 'MpesaReceiptNumber':
                        $parsed['mpesa_receipt_number'] = $item['Value'] ?? null;
                        break;
                    case 'TransactionDate':
                        $parsed['transaction_date'] = $item['Value'] ?? null;
                        break;
                    case 'PhoneNumber':
                        $parsed['phone_number'] = $item['Value'] ?? null;
                        break;
                }
            }
        }

        return $parsed;
    }

    /**
     * Validate callback signature (if implementing webhook security)
     */
    public function validateSignature(array $callbackData, string $secret): bool
    {
        // Implementation would depend on M-Pesa's signature method
        // This is a placeholder for future security implementation
        return true;
    }

    /**
     * Create success response for M-Pesa
     */
    public static function createSuccessResponse(?string $message = null): array
    {
        return [
            'ResultCode' => 0,
            'ResultDesc' => $message ?: 'Success'
        ];
    }

    /**
     * Create error response for M-Pesa
     */
    public static function createErrorResponse(?string $message = null): array
    {
        return [
            'ResultCode' => 1,
            'ResultDesc' => $message ?: 'Error'
        ];
    }

    /**
     * Get formatted transaction details
     */
    public function formatTransactionDetails(array $parsedData): array
    {
        if (!$parsedData['is_successful']) {
            return [
                'status' => 'failed',
                'error' => $parsedData['result_desc'],
                'checkout_request_id' => $parsedData['checkout_request_id']
            ];
        }

        return [
            'status' => 'success',
            'checkout_request_id' => $parsedData['checkout_request_id'],
            'mpesa_receipt_number' => $parsedData['mpesa_receipt_number'],
            'amount' => $parsedData['amount'],
            'phone_number' => $parsedData['phone_number'],
            'transaction_date' => $parsedData['transaction_date'],
            'formatted_date' => $this->formatTransactionDate($parsedData['transaction_date'])
        ];
    }

    /**
     * Format transaction date
     */
    private function formatTransactionDate(?string $transactionDate): ?string
    {
        if (empty($transactionDate)) {
            return null;
        }

        // M-Pesa date format: YYYYMMDDHHMMSS
        if (strlen($transactionDate) === 14) {
            $year = substr($transactionDate, 0, 4);
            $month = substr($transactionDate, 4, 2);
            $day = substr($transactionDate, 6, 2);
            $hour = substr($transactionDate, 8, 2);
            $minute = substr($transactionDate, 10, 2);
            $second = substr($transactionDate, 12, 2);

            return "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
        }

        return $transactionDate;
    }
}