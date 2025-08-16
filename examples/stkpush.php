<?php

/**
 * M-Pesa SDK STK Push Example
 * 
 * Demonstrates how to use the M-Pesa SDK for STK Push (Lipa Na M-Pesa Online) transactions.
 * Shows both basic and advanced usage patterns including status checking.
 * 
 * @package MpesaSDK\Examples
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MpesaSDK\MpesaSDK;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\MpesaException;

// Initialize logger
$logger = new Logger(Logger::LEVEL_INFO, 'mpesa.log');

// Initialize SDK with configuration
$mpesa = MpesaSDK::production([
    'consumer_key' => '',
    'consumer_secret' => '',
    'business_short_code' => '',
    'passkey' => ''
], null, $logger);

// Test connection
if (!$mpesa->testConnection()) {
    die('Failed to connect to M-Pesa API');
}

echo "Connected to M-Pesa " . $mpesa->getEnvironment()['environment'] . " environment\n";

try {
    // STK Push request
    $response = $mpesa->stkPush()->push(
        phoneNumber: '2540115306792',
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
//echo "\n--- Quick Payment Example ---\n";
//
//try {
//    $response = $mpesa->requestPayment(
//        phoneNumber: '254115306792',
//        amount: 5.00,
//        callbackUrl: 'https://yourapp.com/callback/stkpush',
//        accountReference: 'QuickPay123',
//        description: 'Quick Payment'
//    );
//
//    if ($response->isSuccessful()) {
//        echo "Quick payment initiated!\n";
//        echo "Checkout Request ID: " . $response->get('CheckoutRequestID') . "\n";
//    }
//
//} catch (Exception $e) {
//    echo "Quick payment failed: " . $e->getMessage() . "\n";
//}

// Display token info
echo "\n--- Token Info ---\n";
$tokenInfo = $mpesa->getTokenInfo();
echo "Has Token: " . ($tokenInfo['has_token'] ? 'Yes' : 'No') . "\n";
echo "Remaining Seconds: " . $tokenInfo['remaining_seconds'] . "\n";
echo "Will Expire Soon: " . ($tokenInfo['will_expire_soon'] ? 'Yes' : 'No') . "\n";

echo "\nDone!\n";