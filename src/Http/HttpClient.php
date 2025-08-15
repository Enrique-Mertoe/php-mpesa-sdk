<?php

/**
 * M-Pesa SDK HTTP Client
 * 
 * Handles HTTP requests to M-Pesa API endpoints.
 * Provides methods for GET, POST, and PUT requests with proper error handling.
 * 
 * @package MpesaSDK\Http
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Http;

use MpesaSDK\Exceptions\HttpException;
use MpesaSDK\Utils\Logger;

class HttpClient
{
    private $timeout;
    private $logger;

    public function __construct(int $timeout = 30, ?Logger $logger = null)
    {
        $this->timeout = $timeout;
        $this->logger = $logger;
    }

    public function get(string $url, array $headers = []): Response
    {
        return $this->makeRequest('GET', $url, null, $headers);
    }

    public function post(string $url, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('POST', $url, $data, $headers);
    }

    public function put(string $url, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('PUT', $url, $data, $headers);
    }

    private function makeRequest(string $method, string $url, ?array $data = null, array $headers = []): Response
    {
        $curl = curl_init();

        // Basic curl options
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_USERAGENT => 'MpesaSDK/1.0 PHP/' . PHP_VERSION
        ]);

        // Add data for POST/PUT requests
        if ($data !== null && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        // Log request if logger is available
        if ($this->logger) {
            $this->logger->info('HTTP Request', [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'data' => $data
            ]);
        }

        $responseBody = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $curlErrno = curl_errno($curl);

        curl_close($curl);

        if ($curlErrno !== 0) {
            throw new HttpException("cURL error ({$curlErrno}): {$error}");
        }

        if ($responseBody === false) {
            throw new HttpException('Failed to get response from server');
        }

        $response = new Response($httpCode, $responseBody);

        // Log response if logger is available
        if ($this->logger) {
            $this->logger->info('HTTP Response', [
                'status_code' => $httpCode,
                'response' => $response->getBody()
            ]);
        }

        return $response;
    }

    private function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                // Header is already formatted (e.g., "Content-Type: application/json")
                $formatted[] = $value;
            } else {
                // Format key-value pair
                $formatted[] = "{$key}: {$value}";
            }
        }

        return $formatted;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}