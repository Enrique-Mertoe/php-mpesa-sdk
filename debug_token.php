<?php

require_once __DIR__ . '/vendor/autoload.php';

use MpesaSDK\MpesaSDK;
use MpesaSDK\Utils\Logger;

// Initialize logger
$logger = new Logger(Logger::LEVEL_INFO, 'debug.log');

// Initialize SDK
$mpesa = MpesaSDK::sandbox([
    'consumer_key' => 'YENBbIEi5GHaHsy9CzlkQrBESqoYpCXsHK2WrpzBjtGSKg31',
    'consumer_secret' => '64fYZAtqZ72Xt6KVYnaUXrp2HXb0iAVJZ7sibVTCUo8Qx7VE7mBFy6UVuD0zywDO',
    'business_short_code' => '4183161',
    'passkey' => 'cfe77f0c6d37654d134f449d957612e82b6c93c77e1fe592a9ad0651aca2842b'
], null, $logger);

echo "=== CLEARING CACHE AND TESTING FRESH TOKEN ===\n";

// Clear cache and get fresh token
$tokenManager = $mpesa->getTokenManager();
$tokenManager->clearCache();

echo "Cache cleared. Getting fresh token...\n";

try {
    $token = $tokenManager->getAccessToken();
    echo "Fresh token generated: " . substr($token, 0, 10) . "...\n";
    echo "Token length: " . strlen($token) . "\n";
    
    // Test a simple STK Push
    echo "\nTesting STK Push with fresh token...\n";
    
    $response = $mpesa->stkPush()->push(
        phoneNumber: '254115306792',
        amount: 1.00,
        accountReference: 'TestRef',
        transactionDescription: 'Test Payment',
        callbackUrl: 'https://example.com/callback'
    );
    
    if ($response->isSuccessful()) {
        echo "SUCCESS: STK Push initiated!\n";
        echo "Checkout Request ID: " . $response->get('CheckoutRequestID') . "\n";
    } else {
        echo "FAILED: " . $response->getErrorMessage() . "\n";
        echo "Status Code: " . $response->getStatusCode() . "\n";
        echo "Full Response: " . $response->getBody() . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}