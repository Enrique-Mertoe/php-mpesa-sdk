<?php

/**
 * M-Pesa STK Push Service
 * 
 * Handles STK Push (Lipa Na M-Pesa Online) transactions.
 * Provides methods for initiating payments and querying transaction status.
 * 
 * @package MpesaSDK\Services
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Services;

use MpesaSDK\Config\Config;
use MpesaSDK\Auth\TokenManager;
use MpesaSDK\Http\HttpClient;
use MpesaSDK\Http\Response;
use MpesaSDK\Utils\Validator;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\ValidationException;
use MpesaSDK\Exceptions\MpesaException;

class STKPush
{
    private $config;
    private $tokenManager;
    private $httpClient;
    private $logger;

    public function __construct(
        Config $config,
        TokenManager $tokenManager,
        ?HttpClient $httpClient = null,
        ?Logger $logger = null
    ) {
        $this->config = $config;
        $this->tokenManager = $tokenManager;
        $this->httpClient = $httpClient ?: new HttpClient();
        $this->logger = $logger;
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     */
    public function push(
        string $phoneNumber,
        float $amount,
        string $accountReference,
        string $transactionDescription,
        string $callbackUrl
    ): Response {
        // Validate inputs
        $phoneNumber = Validator::validatePhoneNumber($phoneNumber);
        $amount = Validator::validateAmount($amount);
        $accountReference = Validator::validateAccountReference($accountReference);
        $transactionDescription = Validator::validateTransactionDescription($transactionDescription);
        $callbackUrl = Validator::validateCallbackUrl($callbackUrl);

        // Generate timestamp and password
        $timestamp = date('YmdHis');
        $password = $this->generatePassword($timestamp);

        // Prepare payload
        $payload = [
            'BusinessShortCode' => $this->config->getBusinessShortCode(),
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $this->config->getBusinessShortCode(),
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDescription
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('STK Push request initiated', [
                'phone_number' => $phoneNumber,
                'amount' => $amount,
                'account_reference' => $accountReference
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Quick STK Push with minimal parameters
     */
    public function quickPush(
        string $phoneNumber,
        float $amount,
        string $callbackUrl,
        ?string $accountReference = null,
        ?string $transactionDescription = null
    ): Response {
        return $this->push(
            $phoneNumber,
            $amount,
            $accountReference ?: 'Payment',
            $transactionDescription ?: 'Payment',
            $callbackUrl
        );
    }

    /**
     * STK Push with custom business short code and passkey
     */
    public function pushWithCustomCredentials(
        string $phoneNumber,
        float $amount,
        string $accountReference,
        string $transactionDescription,
        string $callbackUrl,
        string $businessShortCode,
        string $passkey
    ): Response {
        // Validate inputs
        $phoneNumber = Validator::validatePhoneNumber($phoneNumber);
        $amount = Validator::validateAmount($amount);
        $accountReference = Validator::validateAccountReference($accountReference);
        $transactionDescription = Validator::validateTransactionDescription($transactionDescription);
        $callbackUrl = Validator::validateCallbackUrl($callbackUrl);
        $businessShortCode = Validator::validateBusinessShortCode($businessShortCode);

        // Generate timestamp and password with custom passkey
        $timestamp = date('YmdHis');
        $password = $this->generateCustomPassword($businessShortCode, $passkey, $timestamp);

        // Prepare payload
        $payload = [
            'BusinessShortCode' => $businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $businessShortCode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDescription
        ];

        return $this->makeRequest($payload);
    }

    /**
     * Query STK Push transaction status
     */
    public function queryStatus(string $checkoutRequestId): Response
    {
        if (empty($checkoutRequestId)) {
            throw new ValidationException('Checkout request ID is required');
        }

        $timestamp = date('YmdHis');
        $password = $this->generatePassword($timestamp);

        $payload = [
            'BusinessShortCode' => $this->config->getBusinessShortCode(),
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];

        $url = str_replace('processrequest', 'stkpushquery', $this->config->getBaseUrl('stkpush'));

        return $this->makeRequest($payload, $url);
    }

    /**
     * Generate password for STK Push
     */
    private function generatePassword(string $timestamp): string
    {
        if (empty($this->config->getPasskey())) {
            throw new MpesaException('Passkey is required for STK Push');
        }

        $dataToEncode = $this->config->getBusinessShortCode() .
            $this->config->getPasskey() .
            $timestamp;

        return base64_encode($dataToEncode);
    }

    /**
     * Generate password with custom credentials
     */
    private function generateCustomPassword(string $businessShortCode, string $passkey, string $timestamp): string
    {
        $dataToEncode = $businessShortCode . $passkey . $timestamp;
        return base64_encode($dataToEncode);
    }

    /**
     * Make HTTP request to M-Pesa API
     */
    private function makeRequest(array $payload, ?string $customUrl = null): Response
    {
        try {
            $url = $customUrl ?: $this->config->getBaseUrl('stkpush');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            // Log response
            if ($this->logger) {
                $this->logger->info('STK Push response received', [
                    'status_code' => $response->getStatusCode(),
                    'successful' => $response->isSuccessful()
                ]);
            }

            // Handle specific M-Pesa error responses
            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                if ($this->logger) {
                    $this->logger->error('STK Push request failed', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage
                    ]);
                }

                throw new MpesaException(
                    "STK Push failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('STK Push request exception', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('STK Push request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate STK Push callback data
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
}