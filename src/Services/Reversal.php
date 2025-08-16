<?php

/**
 * M-Pesa Reversal Service
 * 
 * Handles transaction reversals for M-Pesa transactions.
 * Provides methods to reverse transactions in case of errors or disputes.
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

class Reversal
{
    const IDENTIFIER_TYPE_SHORTCODE = '4';
    const IDENTIFIER_TYPE_MSISDN = '1';
    
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
     * Reverse a transaction
     */
    public function reverse(
        string $transactionId,
        float $amount,
        string $receiverParty,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $receiverIdentifierType = null,
        ?string $occasion = null
    ): Response {
        // Validate inputs
        $transactionId = Validator::validateTransactionId($transactionId);
        $amount = Validator::validateAmount($amount);
        $queueTimeoutUrl = Validator::validateCallbackUrl($queueTimeoutUrl);
        $resultUrl = Validator::validateCallbackUrl($resultUrl);
        
        $receiverIdentifierType = $receiverIdentifierType ?: self::IDENTIFIER_TYPE_SHORTCODE;
        $occasion = $occasion ?: 'Transaction Reversal';

        if (empty($remarks)) {
            throw new ValidationException('Remarks cannot be empty');
        }

        if (empty($this->config->getInitiatorName())) {
            throw new ValidationException('Initiator name is required for transaction reversal');
        }

        if (empty($this->config->getSecurityCredential())) {
            throw new ValidationException('Security credential is required for transaction reversal');
        }

        // Validate receiver party based on identifier type
        if ($receiverIdentifierType === self::IDENTIFIER_TYPE_MSISDN) {
            $receiverParty = Validator::validatePhoneNumber($receiverParty);
        } else {
            $receiverParty = Validator::validateBusinessShortCode($receiverParty);
        }

        // Prepare payload
        $payload = [
            'Initiator' => $this->config->getInitiatorName(),
            'SecurityCredential' => $this->config->getSecurityCredential(),
            'CommandID' => 'TransactionReversal',
            'TransactionID' => $transactionId,
            'Amount' => $amount,
            'ReceiverParty' => $receiverParty,
            'RecieverIdentifierType' => $receiverIdentifierType,
            'ResultURL' => $resultUrl,
            'QueueTimeOutURL' => $queueTimeoutUrl,
            'Remarks' => $remarks,
            'Occasion' => $occasion
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('Transaction reversal initiated', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'receiver_party' => $receiverParty
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Reverse transaction to phone number
     */
    public function reverseToPhoneNumber(
        string $transactionId,
        float $amount,
        string $phoneNumber,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $occasion = null
    ): Response {
        return $this->reverse(
            $transactionId,
            $amount,
            $phoneNumber,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            self::IDENTIFIER_TYPE_MSISDN,
            $occasion
        );
    }

    /**
     * Reverse transaction to business short code
     */
    public function reverseToShortCode(
        string $transactionId,
        float $amount,
        string $shortCode,
        string $remarks,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $occasion = null
    ): Response {
        return $this->reverse(
            $transactionId,
            $amount,
            $shortCode,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl,
            self::IDENTIFIER_TYPE_SHORTCODE,
            $occasion
        );
    }

    /**
     * Make HTTP request to M-Pesa API
     */
    private function makeRequest(array $payload): Response
    {
        try {
            $url = $this->config->getBaseUrl('reversal');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            // Log response
            if ($this->logger) {
                $this->logger->info('Reversal response received', [
                    'status_code' => $response->getStatusCode(),
                    'successful' => $response->isSuccessful()
                ]);
            }

            // Handle specific M-Pesa error responses
            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                if ($this->logger) {
                    $this->logger->error('Transaction reversal failed', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage
                    ]);
                }

                throw new MpesaException(
                    "Transaction reversal failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transaction reversal exception', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('Transaction reversal failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate reversal callback data
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
            'transaction_id' => $this->extractResultParameter($result, 'TransactionID'),
            'working_account_funds' => $this->extractResultParameter($result, 'WorkingAccountAvailableFunds'),
            'utility_account_funds' => $this->extractResultParameter($result, 'UtilityAccountAvailableFunds'),
            'charges_paid_account_funds' => $this->extractResultParameter($result, 'ChargesPaidAccountAvailableFunds'),
            'completed_time' => $this->extractResultParameter($result, 'TransactionCompletedDateTime'),
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