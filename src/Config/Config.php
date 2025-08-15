<?php

namespace MpesaSDK\Config;

use MpesaSDK\Exceptions\MpesaException;

class Config
{
    const ENVIRONMENT_SANDBOX = 'sandbox';
    const ENVIRONMENT_PRODUCTION = 'production';

    private $environment;
    private $consumerKey;
    private $consumerSecret;
    private $businessShortCode;
    private $passkey;
    private $initiatorName;
    private $securityCredential;
    private $baseUrls;

    public function __construct(array $config = [])
    {
        $this->environment = $config['environment'] ?? self::ENVIRONMENT_SANDBOX;
        $this->consumerKey = $config['consumer_key'] ?? '';
        $this->consumerSecret = $config['consumer_secret'] ?? '';
        $this->businessShortCode = $config['business_short_code'] ?? '';
        $this->passkey = $config['passkey'] ?? '';
        $this->initiatorName = $config['initiator_name'] ?? '';
        $this->securityCredential = $config['security_credential'] ?? '';

        $this->setBaseUrls();
        $this->validate();
    }

    private function setBaseUrls(): void
    {
        if ($this->environment === self::ENVIRONMENT_PRODUCTION) {
            $this->baseUrls = [
                'oauth' => 'https://api.safaricom.co.ke/oauth/v1/generate',
                'stkpush' => 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
                'c2b_register' => 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl',
                'c2b_simulate' => 'https://api.safaricom.co.ke/mpesa/c2b/v1/simulate',
                'b2c' => 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest',
                'b2b' => 'https://api.safaricom.co.ke/mpesa/b2b/v1/paymentrequest',
                'account_balance' => 'https://api.safaricom.co.ke/mpesa/accountbalance/v1/query',
                'transaction_status' => 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query',
                'reversal' => 'https://api.safaricom.co.ke/mpesa/reversal/v1/request'
            ];
        } else {
            $this->baseUrls = [
                'oauth' => 'https://sandbox.safaricom.co.ke/oauth/v1/generate',
                'stkpush' => 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
                'c2b_register' => 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl',
                'c2b_simulate' => 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate',
                'b2c' => 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest',
                'b2b' => 'https://sandbox.safaricom.co.ke/mpesa/b2b/v1/paymentrequest',
                'account_balance' => 'https://sandbox.safaricom.co.ke/mpesa/accountbalance/v1/query',
                'transaction_status' => 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query',
                'reversal' => 'https://sandbox.safaricom.co.ke/mpesa/reversal/v1/request'
            ];
        }
    }

    private function validate(): void
    {
        if (empty($this->consumerKey)) {
            throw new MpesaException('Consumer key is required');
        }

        if (empty($this->consumerSecret)) {
            throw new MpesaException('Consumer secret is required');
        }

        if (!in_array($this->environment, [self::ENVIRONMENT_SANDBOX, self::ENVIRONMENT_PRODUCTION])) {
            throw new MpesaException('Invalid environment. Use sandbox or production');
        }
    }

    // Getters
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getConsumerKey(): string
    {
        return $this->consumerKey;
    }

    public function getConsumerSecret(): string
    {
        return $this->consumerSecret;
    }

    public function getBusinessShortCode(): string
    {
        return $this->businessShortCode;
    }

    public function getPasskey(): string
    {
        return $this->passkey;
    }

    public function getInitiatorName(): string
    {
        return $this->initiatorName;
    }

    public function getSecurityCredential(): string
    {
        return $this->securityCredential;
    }

    public function getBaseUrl(string $endpoint): string
    {
        if (!isset($this->baseUrls[$endpoint])) {
            throw new MpesaException("Unknown endpoint: {$endpoint}");
        }

        return $this->baseUrls[$endpoint];
    }

    public function isProduction(): bool
    {
        return $this->environment === self::ENVIRONMENT_PRODUCTION;
    }

    public function isSandbox(): bool
    {
        return $this->environment === self::ENVIRONMENT_SANDBOX;
    }

    // Static factory methods
    public static function sandbox(array $config): self
    {
        $config['environment'] = self::ENVIRONMENT_SANDBOX;
        return new self($config);
    }

    public static function production(array $config): self
    {
        $config['environment'] = self::ENVIRONMENT_PRODUCTION;
        return new self($config);
    }

    public static function fromEnv(): self
    {
        return new self([
            'environment' => $_ENV['MPESA_ENVIRONMENT'] ?? self::ENVIRONMENT_SANDBOX,
            'consumer_key' => $_ENV['MPESA_CONSUMER_KEY'] ?? '',
            'consumer_secret' => $_ENV['MPESA_CONSUMER_SECRET'] ?? '',
            'business_short_code' => $_ENV['MPESA_BUSINESS_SHORT_CODE'] ?? '',
            'passkey' => $_ENV['MPESA_PASSKEY'] ?? '',
            'initiator_name' => $_ENV['MPESA_INITIATOR_NAME'] ?? '',
            'security_credential' => $_ENV['MPESA_SECURITY_CREDENTIAL'] ?? ''
        ]);
    }
}