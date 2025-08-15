<?php

namespace MpesaSDK\Services;

use MpesaSDK\Config\Config;
use MpesaSDK\Auth\TokenManager;
use MpesaSDK\Http\HttpClient;
use MpesaSDK\Http\Response;
use MpesaSDK\Utils\Validator;
use MpesaSDK\Utils\SecurityCredential;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\ValidationException;
use MpesaSDK\Exceptions\MpesaException;

class B2C
{
    const COMMAND_SALARY_PAYMENT = 'SalaryPayment';
    const COMMAND_BUSINESS_PAYMENT = 'BusinessPayment';
    const COMMAND_PROMOTION_PAYMENT = 'PromotionPayment';

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
     * Send money to customer (Business to Customer)
     */
    public function send(
        string $phoneNumber,
        float $amount,
        string $commandId,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $occasion = null
    ): Response {
        // Validate inputs
        $phoneNumber = Validator::validatePhoneNumber($phoneNumber);
        $amount = Validator::validateAmount($amount);
        $commandId = Validator::validateCommandId($commandId);
        $queueTimeoutUrl = Validator::validateCallbackUrl($queueTimeoutUrl);
        $resultUrl = Validator::validateCallbackUrl($resultUrl);

        if (empty($remarks)) {
            throw new ValidationException('Remarks cannot be empty');
        }

        // Validate command ID for B2C
        if (!in_array($commandId, [
            self::COMMAND_SALARY_PAYMENT,
            self::COMMAND_BUSINESS_PAYMENT,
            self::COMMAND_PROMOTION_PAYMENT
        ])) {
            throw new ValidationException('Invalid command ID for B2C transaction');
        }

        // Prepare payload
        $payload = [
            'InitiatorName' => $this->config->getInitiatorName(),
            'SecurityCredential' => $this->config->getSecurityCredential(),
            'CommandID' => $commandId,
            'Amount' => $amount,
            'PartyA' => $this->config->getBusinessShortCode(),
            'PartyB' => $phoneNumber,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $queueTimeoutUrl,
            'ResultURL' => $resultUrl,
            'Occasion' => $occasion ?: $remarks
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('B2C payment initiated', [
                'phone_number' => $phoneNumber,
                'amount' => $amount,
                'command_id' => $commandId
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Send salary payment
     */
    public function sendSalary(
        string $phoneNumber,
        float $amount,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $occasion = null
    ): Response {
        return $this->send(
            $phoneNumber,
            $amount,
            self::COMMAND_SALARY_PAYMENT,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            $occasion
        );
    }

    /**
     * Send business payment
     */
    public function sendBusinessPayment(
        string $phoneNumber,
        float $amount,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $occasion = null
    ): Response {
        return $this->send(
            $phoneNumber,
            $amount,
            self::COMMAND_BUSINESS_PAYMENT,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            $occasion
        );
    }

    /**
     * Send promotion payment
     */
    public function sendPromotion(
        string $phoneNumber,
        float $amount,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $occasion = null
    ): Response {
        return $this->send(
            $phoneNumber,
            $amount,
            self::COMMAND_PROMOTION_PAYMENT,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            $occasion
        );
    }

    /**
     * Bulk B2C payments
     */
    public function sendBulk(array $payments, string $queueTimeoutUrl, string $resultUrl): array
    {
        $results = [];

        foreach ($payments as $index => $payment) {
            try {
                $result = $this->send(
                    $payment['phone_number'],
                    $payment['amount'],
                    $payment['command_id'] ?? self::COMMAND_BUSINESS_PAYMENT,
                    $payment['remarks'],
                    $queueTimeoutUrl,
                    $resultUrl,
                    $payment['occasion'] ?? null
                );

                $results[$index] = [
                    'success' => true,
                    'response' => $result->getData(),
                    'payment' => $payment
                ];

            } catch (\Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'payment' => $payment
                ];

                if ($this->logger) {
                    $this->logger->error('Bulk B2C payment failed', [
                        'index' => $index,
                        'payment' => $payment,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add small delay between requests to avoid rate limiting
            if ($index < count($payments) - 1) {
                usleep(500000); // 0.5 seconds
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
            $url = $this->config->getBaseUrl('b2c');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            // Log response
            if ($this->logger) {
                $this->logger->info('B2C response received', [
                    'status_code' => $response->getStatusCode(),
                    'successful' => $response->isSuccessful()
                ]);
            }

            // Handle specific M-Pesa error responses
            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                if ($this->logger) {
                    $this->logger->error('B2C request failed', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage
                    ]);
                }

                throw new MpesaException(
                    "B2C payment failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('B2C request exception', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('B2C payment request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate B2C callback data
     */
    public function validateCallback(array $callbackData): array
    {
        $required = ['Result'];
        Validator::validateRequired($callbackData, $required);

        $result = $callbackData['Result'];

        return [
            'conversation_id' => $result['ConversationID'] ?? '',
            'originator_conversation_id' => $result['OriginatorConversationID'] ?? '',
            'result_code' => $result['ResultCode'] ?? '',
            'result_desc' => $result['ResultDesc'] ?? '',
            'transaction_id' => $this->extractResultParameter($result, 'TransactionReceipt'),
            'transaction_amount' => $this->extractResultParameter($result, 'TransactionAmount'),
            'working_account_funds' => $this->extractResultParameter($result, 'B2CWorkingAccountAvailableFunds'),
            'utility_account_funds' => $this->extractResultParameter($result, 'B2CUtilityAccountAvailableFunds'),
            'transaction_completed_time' => $this->extractResultParameter($result, 'TransactionCompletedDateTime'),
            'receiver_party_public_name' => $this->extractResultParameter($result, 'ReceiverPartyPublicName'),
            'charges_paid_account_funds' => $this->extractResultParameter($result, 'B2CChargesPaidAccountAvailableFunds'),
            'recipient_is_registered_customer' => $this->extractResultParameter($result, 'RecipientIsRegisteredCustomer'),
            'is_successful' => ($result['ResultCode'] ?? 1) == 0
        ];
    }

    /**
     * Extract parameter from callback result parameters
     */
    private function extractResultParameter(array $result, string $key): ?string
    {
        if (!isset($result['ResultParameters']['ResultParameter'])) {
            return null;
        }

        foreach ($result['ResultParameters']['ResultParameter'] as $param) {
            if ($param['Key'] === $key) {
                return $param['Value'] ?? null;
            }
        }

        return null;
    }
}