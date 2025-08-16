<?php

/**
 * M-Pesa B2B (Business to Business) Service
 * 
 * Handles Business to Business transactions including business transfers,
 * business buy goods, and other B2B payment operations.
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
use MpesaSDK\Utils\SecurityCredential;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\ValidationException;
use MpesaSDK\Exceptions\MpesaException;

class B2B
{
    const COMMAND_BUSINESS_TO_BUSINESS = 'BusinessToBusinessTransfer';
    const COMMAND_BUSINESS_BUY_GOODS = 'BusinessBuyGoods';
    const COMMAND_DISBURSE_FUNDS = 'DisburseFundsToBusiness';
    const COMMAND_BUSINESS_PAY_BILL = 'BusinessPayBill';

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
     * Send money to another business (Business to Business)
     */
    public function send(
        string $receiverShortCode,
        float $amount,
        string $commandId,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $accountReference = null,
        ?string $requester = null
    ): Response {
        // Validate inputs
        $receiverShortCode = Validator::validateBusinessShortCode($receiverShortCode);
        $amount = Validator::validateAmount($amount);
        $commandId = Validator::validateCommandId($commandId);
        $queueTimeoutUrl = Validator::validateCallbackUrl($queueTimeoutUrl);
        $resultUrl = Validator::validateCallbackUrl($resultUrl);

        if (empty($remarks)) {
            throw new ValidationException('Remarks cannot be empty');
        }

        // Validate command ID for B2B
        if (!in_array($commandId, [
            self::COMMAND_BUSINESS_TO_BUSINESS,
            self::COMMAND_BUSINESS_BUY_GOODS,
            self::COMMAND_DISBURSE_FUNDS,
            self::COMMAND_BUSINESS_PAY_BILL
        ])) {
            throw new ValidationException('Invalid command ID for B2B transaction');
        }

        // Prepare payload
        $payload = [
            'Initiator' => $this->config->getInitiatorName(),
            'SecurityCredential' => $this->config->getSecurityCredential(),
            'CommandID' => $commandId,
            'SenderIdentifierType' => '4', // Organization shortcode
            'RecieverIdentifierType' => '4', // Organization shortcode
            'Amount' => $amount,
            'PartyA' => $this->config->getBusinessShortCode(),
            'PartyB' => $receiverShortCode,
            'AccountReference' => $accountReference ?: $remarks,
            'Requester' => $requester ?: $this->config->getBusinessShortCode(),
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $queueTimeoutUrl,
            'ResultURL' => $resultUrl
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('B2B payment initiated', [
                'receiver' => $receiverShortCode,
                'amount' => $amount,
                'command_id' => $commandId
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Business to Business transfer
     */
    public function transfer(
        string $receiverShortCode,
        float $amount,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $accountReference = null
    ): Response {
        return $this->send(
            $receiverShortCode,
            $amount,
            self::COMMAND_BUSINESS_TO_BUSINESS,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            $accountReference
        );
    }

    /**
     * Business buy goods
     */
    public function buyGoods(
        string $tillNumber,
        float $amount,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $accountReference = null
    ): Response {
        return $this->send(
            $tillNumber,
            $amount,
            self::COMMAND_BUSINESS_BUY_GOODS,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            $accountReference
        );
    }

    /**
     * Pay bill to another business
     */
    public function payBill(
        string $receiverShortCode,
        float $amount,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $accountReference = null
    ): Response {
        return $this->send(
            $receiverShortCode,
            $amount,
            self::COMMAND_BUSINESS_PAY_BILL,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            $accountReference
        );
    }

    /**
     * Bulk B2B payments
     */
    public function sendBulk(array $payments, string $queueTimeoutUrl, string $resultUrl): array
    {
        $results = [];

        foreach ($payments as $index => $payment) {
            try {
                $result = $this->send(
                    $payment['receiver_short_code'],
                    $payment['amount'],
                    $payment['command_id'] ?? self::COMMAND_BUSINESS_TO_BUSINESS,
                    $payment['remarks'],
                    $queueTimeoutUrl,
                    $resultUrl,
                    $payment['account_reference'] ?? null,
                    $payment['requester'] ?? null
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
                    $this->logger->error('Bulk B2B payment failed', [
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
            $url = $this->config->getBaseUrl('b2b');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            // Log response
            if ($this->logger) {
                $this->logger->info('B2B response received', [
                    'status_code' => $response->getStatusCode(),
                    'successful' => $response->isSuccessful()
                ]);
            }

            // Handle specific M-Pesa error responses
            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                if ($this->logger) {
                    $this->logger->error('B2B request failed', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage
                    ]);
                }

                throw new MpesaException(
                    "B2B payment failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('B2B request exception', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('B2B payment request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate B2B callback data
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
            'debit_account_balance' => $this->extractResultParameter($result, 'DebitAccountBalance'),
            'amount' => $this->extractResultParameter($result, 'Amount'),
            'debit_party_charges' => $this->extractResultParameter($result, 'DebitPartyCharges'),
            'receiver_party_public_name' => $this->extractResultParameter($result, 'ReceiverPartyPublicName'),
            'debit_party_affected_account_balance' => $this->extractResultParameter($result, 'DebitPartyAffectedAccountBalance'),
            'transaction_completed_time' => $this->extractResultParameter($result, 'TransCompletedTime'),
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