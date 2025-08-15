<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MpesaSDK\MpesaSDK;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\MpesaException;

// Initialize logger
$logger = new Logger(Logger::LEVEL_INFO, 'mpesa.log');

// Initialize SDK with configuration
$mpesa = MpesaSDK::sandbox([
    'consumer_key' => 'Ouu0rMh6WAmOlUkoEBb90HoeR7YErbzE',
    'consumer_secret' => 'xfDlFggwSglAMdoL',
    'business_short_code' => '174379',
    'passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'
], null, $logger);

// Test connection
if (!$mpesa->testConnection()) {
    die('Failed to connect to M-Pesa API');
}

echo "Connected to M-Pesa " . $mpesa->getEnvironment()['environment'] . " environment\n";

try {
    // STK Push request
    $response = $mpesa->stkPush()->push(
        phoneNumber: '254714356761',
        amount: 10.00,
        accountReference: 'OrderNo123',
        transactionDescription: 'Payment',
        callbackUrl: 'https://yourapp.com/callback/stkpush'
    );

    if ($response->isSuccessful()) {
        echo "STK Push initiated successfully!\n";
        echo "Checkout Request ID: " . $response->get('CheckoutRequestID') . "\n";
        echo "Merchant Request ID: " . $response->get('MerchantRequestID') . "\n";
        echo "Response Description: " . $response->get('ResponseDescription') . "\n";

        // Store the checkout request ID for status checking
        $checkoutRequestId = $response->get('CheckoutRequestID');

        // Wait a bit and then check status
        echo "\nWaiting 30 seconds before checking status...\n";
        sleep(30);

        $statusResponse = $mpesa->stkPush()->queryStatus($checkoutRequestId);

        if ($statusResponse->isSuccessful()) {
            echo "Transaction Status: " . $statusResponse->get('ResultDesc') . "\n";
        } else {
            echo "Failed to check status: " . $statusResponse->getErrorMessage() . "\n";
        }

    } else {
        echo "STK Push failed: " . $response->getErrorMessage() . "\n";
    }

} catch (MpesaException $e) {
    echo "M-Pesa Error: " . $e->getFullErrorMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

// Quick payment example using helper method
echo "\n--- Quick Payment Example ---\n";

try {
    $response = $mpesa->requestPayment(
        phoneNumber: '254712345678',
        amount: 5.00,
        callbackUrl: 'https://yourapp.com/callback/stkpush',
        accountReference: 'QuickPay123',
        description: 'Quick Payment'
    );

    if ($response->isSuccessful()) {
        echo "Quick payment initiated!\n";
        echo "Checkout Request ID: " . $response->get('CheckoutRequestID') . "\n";
    }

} catch (Exception $e) {
    echo "Quick payment failed: " . $e->getMessage() . "\n";
}

// Display token info
echo "\n--- Token Info ---\n";
$tokenInfo = $mpesa->getTokenInfo();
echo "Has Token: " . ($tokenInfo['has_token'] ? 'Yes' : 'No') . "\n";
echo "Remaining Seconds: " . $tokenInfo['remaining_seconds'] . "\n";
echo "Will Expire Soon: " . ($tokenInfo['will_expire_soon'] ? 'Yes' : 'No') . "\n";

echo "\nDone!\n";