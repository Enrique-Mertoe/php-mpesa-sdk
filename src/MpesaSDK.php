<?php

namespace MpesaSDK;

use MpesaSDK\Config\Config;
use MpesaSDK\Auth\TokenManager;
use MpesaSDK\Http\HttpClient;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Services\STKPush;
use MpesaSDK\Services\B2C;
use MpesaSDK\Services\B2B;
use MpesaSDK\Services\C2BRegister;
use MpesaSDK\Services\C2BSimulate;
use MpesaSDK\Services\AccountBalance;
use MpesaSDK\Services\TransactionStatus;
use MpesaSDK\Services\Reversal;

/**
 * Main M-Pesa SDK Class
 *
 * Provides a unified interface to all M-Pesa services
 */
class MpesaSDK
{
    private $config;
    private $tokenManager;
    private $httpClient;
    private $logger;

    // Service instances
    private $stkPush;
    private $b2c;
    private $b2b;
    private $c2bRegister;
    private $c2bSimulate;
    private $accountBalance;
    private $transactionStatus;
    private $reversal;

    public function __construct(array|Config $config = [], ?HttpClient $httpClient = null, ?Logger $logger = null)
    {
        $this->config = is_array($config) ? new Config($config) : $config;
        $this->httpClient = $httpClient ?: new HttpClient();
        $this->logger = $logger;
        $this->tokenManager = new TokenManager($this->config, $this->httpClient, $this->logger);
    }

    /**
     * Create SDK instance from environment variables
     */
    public static function fromEnv(?HttpClient $httpClient = null, ?Logger $logger = null): self
    {
        return new self(Config::fromEnv(), $httpClient, $logger);
    }

    /**
     * Create sandbox instance
     */
    public static function sandbox(array $config, ?HttpClient $httpClient = null, ?Logger $logger = null): self
    {
        return new self(Config::sandbox($config), $httpClient, $logger);
    }

    /**
     * Create production instance
     */
    public static function production(array $config, ?HttpClient $httpClient = null, ?Logger $logger = null): self
    {
        return new self(Config::production($config), $httpClient, $logger);
    }

    /**
     * Get STK Push service
     */
    public function stkPush(): STKPush
    {
        if ($this->stkPush === null) {
            $this->stkPush = new STKPush($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->stkPush;
    }

    /**
     * Get B2C service
     */
    public function b2c(): B2C
    {
        if ($this->b2c === null) {
            $this->b2c = new B2C($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->b2c;
    }

    /**
     * Get B2B service
     */
    public function b2b(): B2B
    {
        if ($this->b2b === null) {
            $this->b2b = new B2B($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->b2b;
    }

    /**
     * Get C2B Register service
     */
    public function c2bRegister(): C2BRegister
    {
        if ($this->c2bRegister === null) {
            $this->c2bRegister = new C2BRegister($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->c2bRegister;
    }

    /**
     * Get C2B Simulate service
     */
    public function c2bSimulate(): C2BSimulate
    {
        if ($this->c2bSimulate === null) {
            $this->c2bSimulate = new C2BSimulate($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->c2bSimulate;
    }

    /**
     * Get Account Balance service
     */
    public function accountBalance(): AccountBalance
    {
        if ($this->accountBalance === null) {
            $this->accountBalance = new AccountBalance($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->accountBalance;
    }

    /**
     * Get Transaction Status service
     */
    public function transactionStatus(): TransactionStatus
    {
        if ($this->transactionStatus === null) {
            $this->transactionStatus = new TransactionStatus($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->transactionStatus;
    }

    /**
     * Get Reversal service
     */
    public function reversal(): Reversal
    {
        if ($this->reversal === null) {
            $this->reversal = new Reversal($this->config, $this->tokenManager, $this->httpClient, $this->logger);
        }

        return $this->reversal;
    }

    /**
     * Get configuration
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get token manager
     */
    public function getTokenManager(): TokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Get HTTP client
     */
    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * Get logger
     */
    public function getLogger(): ?Logger
    {
        return $this->logger;
    }

    /**
     * Test connection to M-Pesa API
     */
    public function testConnection(): bool
    {
        try {
            $token = $this->tokenManager->getAccessToken();
            return !empty($token);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Connection test failed', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * Get current access token info
     */
    public function getTokenInfo(): array
    {
        return [
            'has_token' => !empty($this->tokenManager->getAccessToken()),
            'expires_at' => $this->tokenManager->getTokenExpiry(),
            'remaining_seconds' => $this->tokenManager->getRemainingLifetime(),
            'will_expire_soon' => $this->tokenManager->willExpireSoon()
        ];
    }

    /**
     * Clear token cache
     */
    public function clearTokenCache(): void
    {
        $this->tokenManager->clearCache();
    }

    /**
     * Quick STK Push helper method
     */
    public function requestPayment(
        string  $phoneNumber,
        float   $amount,
        string  $callbackUrl,
        ?string $accountReference = null,
        ?string $description = null
    )
    {
        return $this->stkPush()->quickPush(
            $phoneNumber,
            $amount,
            $callbackUrl,
            $accountReference,
            $description
        );
    }

    /**
     * Quick B2C payment helper method
     */
    public function sendMoney(
        string  $phoneNumber,
        float   $amount,
        string  $remarks,
        string  $queueTimeoutUrl,
        string  $resultUrl,
        ?string $commandId = null
    )
    {
        return $this->b2c()->send(
            $phoneNumber,
            $amount,
            $commandId ?: B2C::COMMAND_BUSINESS_PAYMENT,
            $remarks,
            $queueTimeoutUrl,
            $resultUrl
        );
    }

    /**
     * Get SDK version
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get environment info
     */
    public function getEnvironment(): array
    {
        return [
            'environment' => $this->config->getEnvironment(),
            'is_sandbox' => $this->config->isSandbox(),
            'is_production' => $this->config->isProduction(),
            'business_short_code' => $this->config->getBusinessShortCode()
        ];
    }
}