<?php

/**
 * STK Push Unit Tests
 *
 * @package MpesaSDK\Tests\Unit
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Tests\Unit;

use MpesaSDK\Tests\TestCase;
use MpesaSDK\Services\STKPush;
use MpesaSDK\Config\Config;
use MpesaSDK\Auth\TokenManager;
use MpesaSDK\Http\HttpClient;
use MpesaSDK\Http\Response;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\ValidationException;

class STKPushTest extends TestCase
{
    private $stkPush;
    private $mockConfig;
    private $mockTokenManager;
    private $mockHttpClient;
    private $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfig = $this->createMockConfig();
        $this->mockTokenManager = $this->createMock(TokenManager::class);
        $this->mockHttpClient = $this->createMock(HttpClient::class);
        $this->mockLogger = $this->createMock(Logger::class);

        $this->stkPush = new STKPush(
            $this->mockConfig,
            $this->mockTokenManager,
            $this->mockHttpClient,
            $this->mockLogger
        );
    }

    public function testSuccessfulSTKPush()
    {
        $this->mockTokenManager
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('mock_access_token');

        $mockResponse = new Response(200, json_encode([
            'MerchantRequestID' => 'mock_merchant_id',
            'CheckoutRequestID' => 'mock_checkout_id',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing'
        ]));

        $this->mockHttpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn($mockResponse);

        $result = $this->stkPush->push(
            '254714356761',
            100,
            'cxvfxvxv',
            'Test payment',
            'https://example.com/callback'
        );
        print_r($result);

        $this->assertIsArray($result);
        $this->assertEquals('mock_merchant_id', $result['MerchantRequestID']);
        $this->assertEquals('mock_checkout_id', $result['CheckoutRequestID']);
    }

    public function testSTKPushWithInvalidPhoneNumber()
    {
        $this->expectException(ValidationException::class);

        $this->stkPush->push(
            'invalid_phone',
            100,
            'hfghfghgfh',
            'Test payment',
            'https://example.com/callback'
        );
    }

    public function testSTKPushWithInvalidAmount()
    {
        $this->expectException(ValidationException::class);

        $this->stkPush->push(
            '254712345678',
            0,
            'Testpayment',
            'Test payment',
            'https://example.com/callback'
        );
    }

    public function testSTKPushWithInvalidCallbackUrl()
    {
        $this->expectException(ValidationException::class);

        $this->stkPush->push(
            '254712345678',
            100,
            'Testpayment',
            'Test payment',
            'invalid_url'
        );
    }

    public function testQuerySTKPushStatus()
    {
        $this->mockTokenManager
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('mock_access_token');

        $mockResponse = new Response(200, json_encode([
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successfully',
            'MerchantRequestID' => 'mock_merchant_id',
            'CheckoutRequestID' => 'mock_checkout_id',
            'ResultCode' => '0',
            'ResultDesc' => 'The service request is processed successfully.'
        ]));

        $this->mockHttpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn($mockResponse);

        $result = $this->stkPush->query('mock_checkout_id');

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['ResponseCode']);
        $this->assertEquals('mock_checkout_id', $result['CheckoutRequestID']);
    }

    public function testGeneratePassword()
    {
        $config = new Config([
            'short_code' => '174379',
            'passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'
        ]);

        $stkPush = new STKPush(
            $config,
            $this->mockTokenManager,
            $this->mockHttpClient,
            $this->mockLogger
        );

        $timestamp = '20231201120000';
        $password = $stkPush->generatePassword($timestamp);

        $this->assertNotEmpty($password);
        $this->assertEquals(
            base64_encode($config->getShortCode() . $config->getPasskey() . $timestamp),
            $password
        );
    }
}