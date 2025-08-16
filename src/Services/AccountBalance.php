<?php

/**
 * M-Pesa Account Balance Service
 * 
 * Handles account balance queries for M-Pesa business accounts.
 * Provides methods to check working account and utility account balances.
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

class AccountBalance
{
    const IDENTIFIER_TYPE_SHORTCODE = '4';
    
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
     * Query account balance
     */
    public function query(
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $partyA = null,
        ?string $identifierType = null,
        ?string $remarks = null
    ): Response {
        // Validate inputs
        $queueTimeoutUrl = Validator::validateCallbackUrl($queueTimeoutUrl);
        $resultUrl = Validator::validateCallbackUrl($resultUrl);
        
        $partyA = $partyA ?: $this->config->getBusinessShortCode();
        $identifierType = $identifierType ?: self::IDENTIFIER_TYPE_SHORTCODE;
        $remarks = $remarks ?: 'Account Balance Query';

        if (empty($this->config->getInitiatorName())) {
            throw new ValidationException('Initiator name is required for account balance query');
        }

        if (empty($this->config->getSecurityCredential())) {
            throw new ValidationException('Security credential is required for account balance query');
        }

        // Prepare payload
        $payload = [
            'Initiator' => $this->config->getInitiatorName(),
            'SecurityCredential' => $this->config->getSecurityCredential(),
            'CommandID' => 'AccountBalance',
            'PartyA' => $partyA,
            'IdentifierType' => $identifierType,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $queueTimeoutUrl,
            'ResultURL' => $resultUrl
        ];

        // Log request
        if ($this->logger) {
            $this->logger->info('Account balance query initiated', [
                'party_a' => $partyA,
                'identifier_type' => $identifierType
            ]);
        }

        return $this->makeRequest($payload);
    }

    /**
     * Quick account balance query with minimal parameters
     */
    public function quickQuery(
        string $queueTimeoutUrl,
        string $resultUrl
    ): Response {
        return $this->query(
            $queueTimeoutUrl,
            $resultUrl
        );
    }

    /**
     * Query balance for specific short code
     */
    public function queryShortCode(
        string $shortCode,
        string $queueTimeoutUrl,
        string $resultUrl,
        ?string $remarks = null
    ): Response {
        $shortCode = Validator::validateBusinessShortCode($shortCode);
        
        return $this->query(
            $queueTimeoutUrl,
            $resultUrl,
            $shortCode,
            self::IDENTIFIER_TYPE_SHORTCODE,
            $remarks ?: "Balance query for {$shortCode}"
        );
    }

    /**
     * Make HTTP request to M-Pesa API
     */
    private function makeRequest(array $payload): Response
    {
        try {
            $url = $this->config->getBaseUrl('account_balance');

            $headers = array_merge(
                $this->tokenManager->getAuthorizationHeader(),
                ['Content-Type' => 'application/json']
            );

            $response = $this->httpClient->post($url, $payload, $headers);

            // Log response
            if ($this->logger) {
                $this->logger->info('Account balance response received', [
                    'status_code' => $response->getStatusCode(),
                    'successful' => $response->isSuccessful()
                ]);
            }

            // Handle specific M-Pesa error responses
            if (!$response->isSuccessful()) {
                $errorMessage = $response->getErrorMessage();
                $errorCode = $response->getErrorCode();

                if ($this->logger) {
                    $this->logger->error('Account balance query failed', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage
                    ]);
                }

                throw new MpesaException(
                    "Account balance query failed: {$errorMessage}",
                    $response->getStatusCode(),
                    null,
                    $errorCode,
                    $errorMessage
                );
            }

            return $response;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Account balance query exception', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof MpesaException) {
                throw $e;
            }

            throw new MpesaException('Account balance query failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate account balance callback data
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
            'working_account_funds' => $this->extractResultParameter($result, 'AccountBalance'),
            'utility_account_funds' => $this->extractResultParameter($result, 'BOCompletedTime'),
            'charges_paid_account_funds' => $this->extractResultParameter($result, 'ChargesPaidAccountAvailableFunds'),
            'bo_completed_time' => $this->extractResultParameter($result, 'BOCompletedTime'),
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

    /**
     * Parse balance from callback response
     */
    public function parseBalanceResponse(array $callbackData): array
    {
        $validated = $this->validateCallback($callbackData);
        
        if (!$validated['is_successful']) {
            return [
                'success' => false,
                'error' => $validated['result_desc'],
                'error_code' => $validated['result_code']
            ];
        }

        // Parse balance strings (format: "Working Account|KES|123.45|123.45|0|0")
        $workingBalance = $this->parseBalanceString($validated['working_account_funds']);
        $utilityBalance = $this->parseBalanceString($validated['utility_account_funds']);

        return [
            'success' => true,
            'working_account' => $workingBalance,
            'utility_account' => $utilityBalance,
            'charges_paid_account' => $validated['charges_paid_account_funds'],
            'timestamp' => $validated['bo_completed_time'],
            'conversation_id' => $validated['conversation_id']
        ];
    }

    /**
     * Parse balance string into structured data
     */
    private function parseBalanceString(?string $balanceString): array
    {
        if (empty($balanceString)) {
            return [
                'account_type' => 'Unknown',
                'currency' => 'KES',
                'current_balance' => '0.00',
                'available_balance' => '0.00',
                'reserved_balance' => '0.00',
                'uncleared_balance' => '0.00'
            ];
        }

        $parts = explode('|', $balanceString);
        
        return [
            'account_type' => $parts[0] ?? 'Unknown',
            'currency' => $parts[1] ?? 'KES',
            'current_balance' => $parts[2] ?? '0.00',
            'available_balance' => $parts[3] ?? '0.00',
            'reserved_balance' => $parts[4] ?? '0.00',
            'uncleared_balance' => $parts[5] ?? '0.00'
        ];
    }
}