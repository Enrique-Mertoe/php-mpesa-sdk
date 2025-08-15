<?php

/**
 * M-Pesa SDK Token Manager
 * 
 * Manages OAuth access tokens for M-Pesa API authentication.
 * Handles token generation, caching, and expiry management.
 * 
 * @package MpesaSDK\Auth
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Auth;

use MpesaSDK\Config\Config;
use MpesaSDK\Http\HttpClient;
use MpesaSDK\Exceptions\AuthException;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Cache\FileCache;

class TokenManager
{
    private $config;
    private $httpClient;
    private $logger;
    private $tokenCache;
    private $cacheExpiry;
    private $fileCache;
    private $cacheKey;

    public function __construct(Config $config, ?HttpClient $httpClient = null, ?Logger $logger = null, ?FileCache $fileCache = null)
    {
        $this->config = $config;
        $this->httpClient = $httpClient ?: new HttpClient();
        $this->logger = $logger;
        $this->fileCache = $fileCache ?: new FileCache();
        $this->cacheKey = 'mpesa_token_' . md5($config->getConsumerKey() . $config->getEnvironment());
        $this->loadFromCache();
    }

    /**
     * Get a valid access token (cached if available and not expired)
     */
    public function getAccessToken(): string
    {
        if ($this->isTokenValid()) {
            return $this->tokenCache;
        }

        return $this->generateAccessToken();
    }

    /**
     * Force generation of a new access token
     */
    public function generateAccessToken(): string
    {
        try {
            $url = $this->config->getBaseUrl('oauth') . '?grant_type=client_credentials';

            $credentials = base64_encode(
                $this->config->getConsumerKey() . ':' . $this->config->getConsumerSecret()
            );

            $headers = [
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json'
            ];

            if ($this->logger) {
                $this->logger->info('Generating M-Pesa access token');
            }

            $response = $this->httpClient->get($url, $headers);

            if (!$response->isSuccessful()) {
                throw new AuthException(
                    'Failed to generate access token: ' . $response->getErrorMessage(),
                    $response->getStatusCode()
                );
            }

            $token = $response->get('access_token');
            $expiresIn = $response->get('expires_in', 3600);

            if (empty($token)) {
                throw new AuthException('Access token not found in response');
            }

            // Cache the token with expiry (subtract 60 seconds for safety margin)
            $this->tokenCache = $token;
            $this->cacheExpiry = time() + $expiresIn - 60;
            $this->saveToCache($token, $expiresIn - 60);

            if ($this->logger) {
                $this->logger->info('M-Pesa access token generated successfully', [
                    'expires_in' => $expiresIn
                ]);
            }

            return $token;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to generate M-Pesa access token', [
                    'error' => $e->getMessage()
                ]);
            }

            if ($e instanceof AuthException) {
                throw $e;
            }

            throw new AuthException(
                'Failed to generate access token: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if the cached token is still valid
     */
    private function isTokenValid(): bool
    {
        return !empty($this->tokenCache) && time() < $this->cacheExpiry;
    }

    /**
     * Clear the cached token
     */
    public function clearCache(): void
    {
        $this->tokenCache = null;
        $this->cacheExpiry = 0;
        $this->fileCache->delete($this->cacheKey);
    }

    /**
     * Load token from file cache
     */
    private function loadFromCache(): void
    {
        $cached = $this->fileCache->get($this->cacheKey);
        
        if ($cached) {
            $this->tokenCache = $cached['token'];
            $this->cacheExpiry = $cached['expires_at'];
            
            if ($this->logger) {
                $this->logger->info('M-Pesa token loaded from cache', [
                    'expires_at' => date('Y-m-d H:i:s', $this->cacheExpiry)
                ]);
            }
        }
    }

    /**
     * Save token to file cache
     */
    private function saveToCache(string $token, int $ttl): void
    {
        $this->fileCache->set($this->cacheKey, [
            'token' => $token,
            'expires_at' => $this->cacheExpiry
        ], $ttl);
    }

    /**
     * Get token expiry timestamp
     */
    public function getTokenExpiry(): int
    {
        return $this->cacheExpiry;
    }

    /**
     * Check if token will expire soon (within next 5 minutes)
     */
    public function willExpireSoon(): bool
    {
        return $this->cacheExpiry > 0 && ($this->cacheExpiry - time()) < 300;
    }

    /**
     * Get remaining token lifetime in seconds
     */
    public function getRemainingLifetime(): int
    {
        if (!$this->isTokenValid()) {
            return 0;
        }

        return $this->cacheExpiry - time();
    }

    /**
     * Create authorization header with Bearer token
     */
    public function getAuthorizationHeader(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken()
        ];
    }
}