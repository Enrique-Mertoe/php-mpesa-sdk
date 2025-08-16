<?php

/**
 * Token Manager Unit Tests
 *
 * @package MpesaSDK\Tests\Unit
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Tests\Unit;

use MpesaSDK\Tests\TestCase;
use MpesaSDK\Auth\TokenManager;
use MpesaSDK\Config\Config;
use MpesaSDK\Http\HttpClient;
use MpesaSDK\Http\Response;
use MpesaSDK\Cache\FileCache;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\AuthException;

class TokenManagerTest extends TestCase
{
    private $tokenManager;
    private $mockConfig;
    private $mockHttpClient;
    private $mockCache;
    private $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfig = $this->createMockConfig();
        $this->mockHttpClient = $this->createMock(HttpClient::class);
        $this->mockCache = $this->createMock(FileCache::class);
        $this->mockLogger = $this->createMock(Logger::class);

        $this->tokenManager = new TokenManager(
            $this->mockConfig,
            $this->mockHttpClient,
            $this->mockCache,
            $this->mockLogger
        );
    }

    public function testGetAccessTokenFromCache()
    {
        $cachedToken = [
            'access_token' => 'cached_token',
            'expires_in' => 3600
        ];

        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('mpesa_access_token')
            ->willReturn($cachedToken);

        $token = $this->tokenManager->getAccessToken();

        $this->assertEquals('cached_token', $token);
    }

    public function testGetAccessTokenFromApi()
    {
        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('mpesa_access_token')
            ->willReturn(null);

        $mockResponse = new Response(200, json_encode([
            'access_token' => 'new_api_token',
            'expires_in' => '3600'
        ]));

        $this->mockHttpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $this->mockCache
            ->expects($this->once())
            ->method('set')
            ->with(
                'mpesa_access_token',
                ['access_token' => 'new_api_token', 'expires_in' => '3600'],
                3300
            );

        $token = $this->tokenManager->getAccessToken();

        $this->assertEquals('new_api_token', $token);
    }

    public function testGetAccessTokenThrowsExceptionOnApiError()
    {
        $this->expectException(AuthException::class);

        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $mockResponse = new Response(401, json_encode([
            'error' => 'invalid_client',
            'error_description' => 'Invalid client credentials'
        ]));

        $this->mockHttpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $this->tokenManager->getAccessToken();
    }

    public function testGetAccessTokenThrowsExceptionOnMalformedResponse()
    {
        $this->expectException(AuthException::class);

        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $mockResponse = new Response(200, json_encode([
            'token' => 'token_without_access_token_key'
        ]));

        $this->mockHttpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $this->tokenManager->getAccessToken();
    }

    public function testClearToken()
    {
        $this->mockCache
            ->expects($this->once())
            ->method('delete')
            ->with('mpesa_access_token');

        $this->tokenManager->clearToken();
    }

    public function testIsTokenValidWithValidToken()
    {
        $validToken = [
            'access_token' => 'valid_token',
            'expires_in' => 3600
        ];

        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->willReturn($validToken);

        $this->assertTrue($this->tokenManager->isTokenValid());
    }

    public function testIsTokenValidWithNoToken()
    {
        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->assertFalse($this->tokenManager->isTokenValid());
    }
}