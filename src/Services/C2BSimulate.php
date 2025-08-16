<?php

/**
 * M-Pesa C2B Simulate Service
 * 
 * Handles C2B (Customer to Business) transaction simulation for testing M-Pesa integrations.
 * Provides methods to simulate customer payments to business short codes.
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

class C2BSimulate
{
    const COMMAND_CUSTOMER_PAYBILL_ONLINE = 'CustomerPayBillOnline';
    const COMMAND_CUSTOMER_BUY_GOODS_ONLINE = 'CustomerBuyGoodsOnline';
    
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
     * Simulate C2B transaction
     */
    public function simulate(
        string $phoneNumber,
        float $amount,
        string $commandId,
        ?string $shortCode = null,
        ?string $billRefNumber = null
    ): Response {
        // Validate inputs
        $phoneNumber = Validator::validatePhoneNumber($phoneNumber);
        $amount = Validator::validateAmount($amount);
        
        $shortCode = $shortCode ?: $this->config->getBusinessShortCode();
        $billRefNumber = $billRefNumber ?: 'TestPayment';

        if (empty($shortCode)) {
            throw new ValidationException('Business short code is required for C2B simulation');
        }

        $shortCode = Validator::validateBusinessShortCode($shortCode);

        // Validate command ID for C2B
        if (!in_array($commandId, [
            self::COMMAND_CUSTOMER_PAYBILL_ONLINE,
            self::COMMAND_CUSTOMER_BUY_GOODS_ONLINE
        ])) {
            throw new ValidationException('Invalid command ID for C2B transaction. Use CustomerPayBillOnline or CustomerBuyGoodsOnline');
        }

        // Prepare payload
        $payload = [
            'ShortCode' => $shortCode,
            'CommandID' => $commandId,
            'Amount' => $amount,
            'Msisdn' => $phoneNumber,
            'BillRefNumber' => $billRefNumber
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('C2B simulation initiated', [
                'phone_number' => $phoneNumber,
                'amount' => $amount,
                'command_id' => $commandId,
                'short_code' => $shortCode
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Simulate PayBill transaction
     */
    public function simulatePayBill(
        string $phoneNumber,
        float $amount,
        ?string $shortCode = null,
        ?string $billRefNumber = null
    ): Response {
        return $this->simulate(
            $phoneNumber,
            $amount,
            self::COMMAND_CUSTOMER_PAYBILL_ONLINE,
            $shortCode,
            $billRefNumber
        );
    }

    /**
     * Simulate Buy Goods transaction
     */
    public function simulateBuyGoods(
        string $phoneNumber,
        float $amount,
        ?string $tillNumber = null,
        ?string $billRefNumber = null
    ): Response {
        return $this->simulate(
            $phoneNumber,
            $amount,
            self::COMMAND_CUSTOMER_BUY_GOODS_ONLINE,
            $tillNumber,
            $billRefNumber
        );
    }

    /**
     * Quick simulation with minimal parameters
     */
    public function quickSimulate(
        string $phoneNumber,
        float $amount,
        ?string $commandId = null
    ): Response {
        return $this->simulate(
            $phoneNumber,
            $amount,
            $commandId ?: self::COMMAND_CUSTOMER_PAYBILL_ONLINE
        );
    }

    /**
     * Simulate multiple C2B transactions
     */
    public function simulateMultiple(array $transactions): array
    {
        $results = [];

        foreach ($transactions as $index => $transaction) {
            try {
                $result = $this->simulate(
                    $transaction['phone_number'],
                    $transaction['amount'],
                    $transaction['command_id'] ?? self::COMMAND_CUSTOMER_PAYBILL_ONLINE,
                    $transaction['short_code'] ?? null,
                    $transaction['bill_ref_number'] ?? null
                );

                $results[$index] = [
                    'success' => true,
                    'transaction' => $transaction,
                    'response' => $result->getData()
                ];

            } catch (\Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'transaction' => $transaction,
                    'error' => $e->getMessage()
                ];

                if ($this->logger) {
                    $this->logger->error('Multiple C2B simulation failed', [
                        'index' => $index,
                        'transaction' => $transaction,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add small delay between requests to avoid rate limiting
            if ($index < count($transactions) - 1) {
                usleep(300000); // 0.3 seconds
            }
        }

        return $results;
    }

    /**
     * Test C2B integration with predefined test cases
     */
    public function testIntegration(?string $shortCode = null): array
    {
        $shortCode = $shortCode ?: $this->config->getBusinessShortCode();
        
        $testCases = [
            [
                'name' => 'Small Amount PayBill',
                'phone_number' => '254708374149',
                'amount' => 1.00,
                'command_id' => self::COMMAND_CUSTOMER_PAYBILL_ONLINE,
                'bill_ref_number' => 'TEST001'
            ],
            [
                'name' => 'Medium Amount PayBill',
                'phone_number' => '254708374149',
                'amount' => 100.00,
                'command_id' => self::COMMAND_CUSTOMER_PAYBILL_ONLINE,
                'bill_ref_number' => 'TEST002'
            ],
            [
                'name' => 'Buy Goods Transaction',
                'phone_number' => '254708374149',
                'amount' => 50.00,
                'command_id' => self::COMMAND_CUSTOMER_BUY_GOODS_ONLINE,
                'bill_ref_number' => 'TEST003'
            ]
        ];

        $results = [];

        foreach ($testCases as $index => $testCase) {
            try {
                if ($this->logger) {
                    $this->logger->info('Running C2B integration test', [
                        'test_name' => $testCase['name']
                    ]);
                }

                $response = $this->simulate(
                    $testCase['phone_number'],
                    $testCase['amount'],
                    $testCase['command_id'],
                    $shortCode,
                    $testCase['bill_ref_number']
                );

                $results[$testCase['name']] = [
                    'success' => true,
                    'test_case' => $testCase,
                    'response' => $response->getData()
                ];

            } catch (\Exception $e) {
                $results[$testCase['name']] = [
                    'success' => false,
                    'test_case' => $testCase,
                    'error' => $e->getMessage()
                ];

                if ($this->logger) {
                    $this->logger->error('C2B integration test failed', [
                        'test_name' => $testCase['name'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add delay between test cases
            if ($index < count($testCases) - 1) {
                sleep(1);
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
            $url = $this->config->getBaseUrl('c2b_simulate');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            // Log response
            if ($this->logger) {
                $this->logger->info('C2B simulation response received', [
                    'status_code' => $response->getStatusCode(),
                    'successful' => $response->isSuccessful()
                ]);
            }

            // Handle specific M-Pesa error responses
            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                if ($this->logger) {
                    $this->logger->error('C2B simulation failed', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage
                    ]);
                }

                throw new MpesaException(
                    "C2B simulation failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('C2B simulation exception', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('C2B simulation failed: ' . $e->getMessage(), 0, $e);
        }
    }
}