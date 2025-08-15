<?php

namespace MpesaSDK\Callbacks;

use MpesaSDK\Utils\Validator;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\ValidationException;

class STKPushCallback extends CallbackHandler
{
    private $onSuccess;
    private $onFailure;

    public function __construct(?Logger $logger = null)
    {
        parent::__construct($logger);
    }

    /**
     * Set success callback function
     */
    public function onSuccess(callable $callback): self
    {
        $this->onSuccess = $callback;
        return $this;
    }

    /**
     * Set failure callback function
     */
    public function onFailure(callable $callback): self
    {
        $this->onFailure = $callback;
        return $this;
    }

    /**
     * Process STK Push callback from M-Pesa
     */
    public function process(): array
    {
        try {
            // Get callback data from request
            $callbackData = $this->getCallbackData();

            // Log raw callback
            $this->log('STK Push callback received', ['raw_data' => $callbackData]);

            // Validate and parse callback
            $parsedCallback = $this->validateCallback($callbackData);

            $this->log('STK Push callback parsed', ['parsed_data' => $parsedCallback]);

            // Process based on result code
            if ($parsedCallback['is_successful']) {
                $this->handleSuccessfulPayment($parsedCallback);
            } else {
                $this->handleFailedPayment($parsedCallback);
            }

            // Return success response to M-Pesa
            return [
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted'
            ];

        } catch (\Exception $e) {
            $this->log('STK Push callback processing error', ['error' => $e->getMessage()], 'error');

            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate STK Push callback structure
     */
    public function validateCallback(array $callbackData): array
    {
        $required = ['Body'];
        Validator::validateRequired($callbackData, $required);

        if (!isset($callbackData['Body']['stkCallback'])) {
            throw new ValidationException('Invalid STK Push callback structure');
        }

        $callback = $callbackData['Body']['stkCallback'];

        return [
            'merchant_request_id' => $callback['MerchantRequestID'] ?? '',
            'checkout_request_id' => $callback['CheckoutRequestID'] ?? '',
            'result_code' => $callback['ResultCode'] ?? '',
            'result_desc' => $callback['ResultDesc'] ?? '',
            'amount' => $this->extractCallbackValue($callback, 'Amount'),
            'mpesa_receipt_number' => $this->extractCallbackValue($callback, 'MpesaReceiptNumber'),
            'transaction_date' => $this->extractCallbackValue($callback, 'TransactionDate'),
            'phone_number' => $this->extractCallbackValue($callback, 'PhoneNumber'),
            'is_successful' => ($callback['ResultCode'] ?? 1) == 0
        ];
    }

    /**
     * Handle successful payment
     */
    private function handleSuccessfulPayment(array $callbackData): void
    {
        $this->log('STK Push payment successful', [
            'checkout_request_id' => $callbackData['checkout_request_id'],
            'mpesa_receipt' => $callbackData['mpesa_receipt_number'],
            'amount' => $callbackData['amount'],
            'phone_number' => $callbackData['phone_number']
        ]);

        // Call custom success handler if provided
        if ($this->onSuccess) {
            call_user_func($this->onSuccess, $callbackData);
        }

        // Default success handling (can be overridden)
        $this->storeTransaction($callbackData);
    }

    /**
     * Handle failed payment
     */
    private function handleFailedPayment(array $callbackData): void
    {
        $this->log('STK Push payment failed', [
            'checkout_request_id' => $callbackData['checkout_request_id'],
            'result_desc' => $callbackData['result_desc'],
            'result_code' => $callbackData['result_code']
        ], 'warning');

        // Call custom failure handler if provided
        if ($this->onFailure) {
            call_user_func($this->onFailure, $callbackData);
        }

        // Default failure handling
        $this->storeTransaction($callbackData);
    }

    /**
     * Store transaction data (override this method for custom storage)
     */
    protected function storeTransaction(array $callbackData): void
    {
        // Default implementation - you should override this
        // or provide custom handlers via onSuccess/onFailure

        $transactionData = [
            'merchant_request_id' => $callbackData['merchant_request_id'],
            'checkout_request_id' => $callbackData['checkout_request_id'],
            'amount' => $callbackData['amount'] ?: '',
            'mpesa_receipt_number' => $callbackData['mpesa_receipt_number'] ?: '',
            'transaction_date' => $callbackData['transaction_date'] ?: '',
            'status' => $callbackData['is_successful'] ? 'success' : 'failed',
            'phone_number' => $callbackData['phone_number'] ?: '',
            'result_desc' => $callbackData['result_desc'] ?: ''
        ];

        // Log the transaction data
        $this->log('Transaction data prepared for storage', ['transaction' => $transactionData]);

        // You can implement database storage here or use the callback handlers
    }

    /**
     * Extract value from callback metadata
     */
    private function extractCallbackValue(array $callback, string $name): ?string
    {
        if (!isset($callback['CallbackMetadata']['Item'])) {
            return null;
        }

        foreach ($callback['CallbackMetadata']['Item'] as $item) {
            if ($item['Name'] === $name) {
                return $item['Value'] ?? null;
            }
        }

        return null;
    }

    /**
     * Create a standalone callback processor (similar to your Flask route)
     */
    public static function createProcessor(?Logger $logger = null): self
    {
        $processor = new self($logger);

        // Set up default handlers that mirror your Flask implementation
        $processor->onSuccess(function($data) use ($logger) {
            // Mirror your successful payment logic
            if ($logger) {
                $logger->info("Payment successful. Receipt: {$data['mpesa_receipt_number']}, Amount: {$data['amount']}, Phone: {$data['phone_number']}");
            }
        });

        $processor->onFailure(function($data) use ($logger) {
            // Mirror your failed payment logic
            if ($logger) {
                $logger->warning("Payment failed. Reason: {$data['result_desc']}");
            }
        });

        return $processor;
    }
}