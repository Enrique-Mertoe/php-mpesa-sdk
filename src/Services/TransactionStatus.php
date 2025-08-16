<?php

/**
 * M-Pesa Transaction Status Service
 * 
 * Handles transaction status queries for M-Pesa transactions.
 * Provides methods to check the status of any M-Pesa transaction.
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

class TransactionStatus
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
     * Query transaction status
     */
    public function query(
        string $transactionId,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $partyA = null,
        ?string $identifierType = null,
        ?string $remarks = null,
        ?string $occasion = null
    ): Response {
        // Validate inputs
        $transactionId = Validator::validateTransactionId($transactionId);
        $queueTimeoutUrl = Validator::validateCallbackUrl($queueTimeoutUrl);
        $resultUrl = Validator::validateCallbackUrl($resultUrl);
        
        $partyA = $partyA ?: $this->config->getBusinessShortCode();
        $identifierType = $identifierType ?: self::IDENTIFIER_TYPE_SHORTCODE;
        $remarks = $remarks ?: 'Transaction Status Query';
        $occasion = $occasion ?: 'Transaction Status';

        if (empty($this->config->getInitiatorName())) {
            throw new ValidationException('Initiator name is required for transaction status query');
        }

        if (empty($this->config->getSecurityCredential())) {
            throw new ValidationException('Security credential is required for transaction status query');
        }

        // Prepare payload
        $payload = [
            'Initiator' => $this->config->getInitiatorName(),
            'SecurityCredential' => $this->config->getSecurityCredential(),
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $transactionId,
            'PartyA' => $partyA,
            'IdentifierType' => $identifierType,
            'ResultURL' => $resultUrl,
            'QueueTimeOutURL' => $queueTimeoutUrl,
            'Remarks' => $remarks,
            'Occasion' => $occasion
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('Transaction status query initiated', [
                'transaction_id' => $transactionId,
                'party_a' => $partyA
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Make HTTP request to M-Pesa API
     */
    private function makeRequest(array $payload): Response
    {
        try {
            $url = $this->config->getBaseUrl('transaction_status');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                throw new MpesaException(
                    "Transaction status query failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('Transaction status query failed: ' . $e->getMessage(), 0, $e);
        }
    }
}