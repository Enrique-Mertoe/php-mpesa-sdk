<?php

/**
 * Base Test Case for M-Pesa SDK Tests
 *
 * @package MpesaSDK\Tests
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use MpesaSDK\Config\Config;
use MpesaSDK\Auth\TokenManager;
use MpesaSDK\Http\HttpClient;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Cache\FileCache;

abstract class TestCase extends BaseTestCase
{
    protected function createMockConfig(): Config
    {
        return new Config([
            'consumer_key' => 'test_consumer_key',
            'consumer_secret' => 'test_consumer_secret',
            'environment' => 'sandbox',
            'short_code' => '174379',
            'passkey' => 'test_passkey',
            'initiator_name' => 'testapi',
            'security_credential' => 'test_credential',
            'timeout' => 60,
            'cache_ttl' => 3600,
        ]);
    }

    protected function createMockTokenManager(): TokenManager
    {
        $config = $this->createMockConfig();
        $httpClient = $this->createMock(HttpClient::class);
        $cache = $this->createMock(FileCache::class);
        $logger = $this->createMock(Logger::class);

        return new TokenManager($config, $httpClient, $cache, $logger);
    }

    protected function createMockHttpClient(): HttpClient
    {
        return $this->createMock(HttpClient::class);
    }

    protected function createMockLogger(): Logger
    {
        return $this->createMock(Logger::class);
    }
}