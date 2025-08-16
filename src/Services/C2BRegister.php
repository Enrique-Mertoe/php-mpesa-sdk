<?php

/**
 * M-Pesa C2B Register Service
 * 
 * Handles C2B (Customer to Business) URL registration for M-Pesa transactions.
 * Provides methods to register validation and confirmation URLs.
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

class C2BRegister
{
    const RESPONSE_TYPE_COMPLETED = 'Completed';
    const RESPONSE_TYPE_CANCELLED = 'Cancelled';
    
    private Config $config;
    private TokenManager $tokenManager;
    private HttpClient $httpClient;
    private ?Logger $logger;

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
     * Register C2B URLs
     */
    public function register(
        string $validationUrl,
        string $confirmationUrl,
        ?string $shortCode = null,
        ?string $responseType = null
    ): Response {
        // Validate inputs
        $validationUrl = Validator::validateCallbackUrl($validationUrl);
        $confirmationUrl = Validator::validateCallbackUrl($confirmationUrl);
        
        $shortCode = $shortCode ?: $this->config->getBusinessShortCode();
        $responseType = $responseType ?: self::RESPONSE_TYPE_COMPLETED;

        if (empty($shortCode)) {
            throw new ValidationException('Business short code is required for C2B registration');
        }

        $shortCode = Validator::validateBusinessShortCode($shortCode);

        // Validate response type
        if (!in_array($responseType, [self::RESPONSE_TYPE_COMPLETED, self::RESPONSE_TYPE_CANCELLED])) {
            throw new ValidationException('Invalid response type. Use Completed or Cancelled');
        }

        // Prepare payload
        $payload = [
            'ShortCode' => $shortCode,
            'ResponseType' => $responseType,
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL' => $validationUrl
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('C2B URL registration initiated', [
                'short_code' => $shortCode,
                'response_type' => $responseType
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Register C2B URLs with completed response type
     */
    public function registerCompleted(
        string $validationUrl,
        string $confirmationUrl,
        ?string $shortCode = null
    ): Response {
        return $this->register(
            $validationUrl,
            $confirmationUrl,
            $shortCode,
            self::RESPONSE_TYPE_COMPLETED
        );
    }

    /**
     * Register C2B URLs with cancelled response type
     */
    public function registerCancelled(
        string $validationUrl,
        string $confirmationUrl,
        ?string $shortCode = null
    ): Response {
        return $this->register(
            $validationUrl,
            $confirmationUrl,
            $shortCode,
            self::RESPONSE_TYPE_CANCELLED
        );
    }

    /**
     * Quick C2B registration with minimal parameters
     */
    public function quickRegister(
        string $confirmationUrl,
        ?string $validationUrl = null
    ): Response {
        $validationUrl = $validationUrl ?: $confirmationUrl;
        
        return $this->register(
            $validationUrl,
            $confirmationUrl
        );
    }

    /**
     * Register multiple short codes
     */
    public function registerMultiple(
        array $registrations,
        string $validationUrl,
        string $confirmationUrl
    ): array {
        $results = [];

        foreach ($registrations as $index => $registration) {
            try {
                $result = $this->register(
                    $validationUrl,
                    $confirmationUrl,
                    $registration['short_code'],
                    $registration['response_type'] ?? self::RESPONSE_TYPE_COMPLETED
                );

                $results[$index] = [
                    'success' => true,
                    'short_code' => $registration['short_code'],
                    'response' => $result->getData()
                ];

            } catch (\Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'short_code' => $registration['short_code'],
                    'error' => $e->getMessage()
                ];

                if ($this->logger) {
                    $this->logger->error('Multiple C2B registration failed', [
                        'index' => $index,
                        'short_code' => $registration['short_code'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add small delay between requests to avoid rate limiting
            if ($index < count($registrations) - 1) {
                usleep(200000); // 0.2 seconds
            }
        }

        return $results;
    }

    /**
     * Make HTTP request to M-Pesa API
     */
    private function makeRequest(array $payload): Response
    {
        try {
            $url = $this->config->getBaseUrl('c2b_register');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            // Log response
            if ($this->logger) {
                $this->logger->info('C2B registration response received', [
                    'status_code' => $response->getStatusCode(),
                    'successful' => $response->isSuccessful()
                ]);
            }

            // Handle specific M-Pesa error responses
            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                if ($this->logger) {
                    $this->logger->error('C2B registration failed', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage
                    ]);
                }

                throw new MpesaException(
                    "C2B registration failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('C2B registration exception', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('C2B registration failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate C2B validation request data
     */
    public function validateValidationRequest(array $requestData): array
    {
        $required = ['TransactionType', 'TransID', 'TransTime', 'TransAmount', 'BusinessShortCode', 'BillRefNumber', 'InvoiceNumber', 'OrgAccountBalance', 'ThirdPartyTransID', 'MSISDN', 'FirstName', 'MiddleName', 'LastName'];
        
        // Check if all required fields are present
        $missing = [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $requestData)) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            if ($this->logger) {
                $this->logger->warning('C2B validation request missing fields', [
                    'missing_fields' => $missing
                ]);
            }
        }

        return [
            'transaction_type' => $requestData['TransactionType'] ?? '',
            'trans_id' => $requestData['TransID'] ?? '',
            'trans_time' => $requestData['TransTime'] ?? '',
            'trans_amount' => $requestData['TransAmount'] ?? '',
            'business_short_code' => $requestData['BusinessShortCode'] ?? '',
            'bill_ref_number' => $requestData['BillRefNumber'] ?? '',
            'invoice_number' => $requestData['InvoiceNumber'] ?? '',
            'org_account_balance' => $requestData['OrgAccountBalance'] ?? '',
            'third_party_trans_id' => $requestData['ThirdPartyTransID'] ?? '',
            'msisdn' => $requestData['MSISDN'] ?? '',
            'first_name' => $requestData['FirstName'] ?? '',
            'middle_name' => $requestData['MiddleName'] ?? '',
            'last_name' => $requestData['LastName'] ?? ''
        ];
    }

    /**
     * Create validation response
     */
    public function createValidationResponse(bool $accept = true, ?string $message = null): array
    {
        return [
            'ResultCode' => $accept ? 0 : 1,
            'ResultDesc' => $message ?: ($accept ? 'Accepted' : 'Rejected')
        ];
    }

    /**
     * Validate C2B confirmation request data
     */
    public function validateConfirmationRequest(array $requestData): array
    {
        return $this->validateValidationRequest($requestData);
    }

    /**
     * Create confirmation response
     */
    public function createConfirmationResponse(?string $message = null): array
    {
        return [
            'ResultCode' => 0,
            'ResultDesc' => $message ?: 'Success'
        ];
    }
}