<?php

/**
 * Config Unit Tests
 *
 * @package MpesaSDK\Tests\Unit
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Tests\Unit;

use MpesaSDK\Tests\TestCase;
use MpesaSDK\Config\Config;
use MpesaSDK\Exceptions\ConfigException;

class ConfigTest extends TestCase
{
    public function testConfigCreation()
    {
        $config = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox'
        ]);

        $this->assertEquals('test_key', $config->getConsumerKey());
        $this->assertEquals('test_secret', $config->getConsumerSecret());
        $this->assertEquals('sandbox', $config->getEnvironment());
    }

    public function testSandboxUrls()
    {
        $config = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox'
        ]);

        $this->assertEquals('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials', $config->getAuthUrl());
        $this->assertEquals('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', $config->getStkPushUrl());
        $this->assertEquals('https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest', $config->getB2CUrl());
    }

    public function testProductionUrls()
    {
        $config = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox'
        ]);

        $this->assertEquals('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials', $config->getAuthUrl());
        $this->assertEquals('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest', $config->getStkPushUrl());
        $this->assertEquals('https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest', $config->getB2CUrl());
    }

    public function testInvalidEnvironmentThrowsException()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid environment: invalid. Must be sandbox or production');

        new Config(['environment' => 'invalid']);
    }

    public function testDefaultValues()
    {
        $config = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret'
        ]);

        $this->assertEquals('sandbox', $config->getEnvironment());
        $this->assertEquals(60, $config->getTimeout());
        $this->assertEquals(3600, $config->getCacheTtl());
    }

    public function testGetAllConfig()
    {
        $configArray = [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'production',
            'timeout' => 120
        ];

        $config = new Config($configArray);
        $result = $config->all();

        $this->assertArrayHasKey('consumer_key', $result);
        $this->assertArrayHasKey('consumer_secret', $result);
        $this->assertArrayHasKey('environment', $result);
        $this->assertArrayHasKey('timeout', $result);
        $this->assertEquals('test_key', $result['consumer_key']);
        $this->assertEquals('production', $result['environment']);
        $this->assertEquals(120, $result['timeout']);
    }

    public function testIsSandbox()
    {
        $sandboxConfig = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox'
        ]);
        $productionConfig = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'production'
        ]);

        $this->assertTrue($sandboxConfig->isSandbox());
        $this->assertFalse($productionConfig->isSandbox());
    }

    public function testIsProduction()
    {
        $sandboxConfig = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox'
        ]);
        $productionConfig = new Config([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'production'
        ]);

        $this->assertFalse($sandboxConfig->isProduction());
        $this->assertTrue($productionConfig->isProduction());
    }
}