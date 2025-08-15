<?php

/**
 * M-Pesa SDK STK Push Callback Example
 * 
 * Basic example of handling STK Push callbacks using the M-Pesa SDK.
 * Shows validation, parsing, and response handling.
 * 
 * @package MpesaSDK\Examples\Callbacks
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MpesaSDK\Services\STKPush;
use MpesaSDK\Config\Config;
use MpesaSDK\Auth\TokenManager;
use MpesaSDK\Utils\Logger;
use MpesaSDK\Exceptions\ValidationException;

// Initialize logger for callback processing
$logger = new Logger(Logger::LEVEL_INFO, __DIR__ . '/../../logs/callback.log');

// Set response headers
header('Content-Type: application/json');
http_response_code(200);

try {
    // Get callback data from M-Pesa
    $callbackData = json_decode(file_get_contents('php://input'), true);

    if (empty($callbackData)) {
        throw new ValidationException('Empty callback data received');
    }

    // Log raw callback data
    $logger->info('STK Push callback received', ['raw_data' => $callbackData]);

    // Initialize STK Push service for validation
    $config = Config::sandbox([
        'consumer_key' => 'your_consumer_key',
        'consumer_secret' => 'your_consumer_secret',
        'business_short_code' => '174379',
        'passkey' => 'your_passkey'
    ]);

    $tokenManager = new TokenManager($config);
    $stkPush = new STKPush($config, $tokenManager, null, $logger);

    // Validate and parse callback data
    $parsedCallback = $stkPush->validateCallback($callbackData);

    $logger->info('STK Push callback parsed', ['parsed_data' => $parsedCallback]);

    // Process the callback based on result
    if ($parsedCallback['is_successful']) {
        // Payment was successful
        $logger->info('STK Push payment successful', [
            'checkout_request_id' => $parsedCallback['checkout_request_id'],
            'mpesa_receipt' => $parsedCallback['mpesa_receipt_number'],
            'amount' => $parsedCallback['amount'],
            'phone_number' => $parsedCallback['phone_number']
        ]);

        // Update your database/system with successful payment
        processSuccessfulPayment($parsedCallback);

    } else {
        // Payment failed or was cancelled
        $logger->warning('STK Push payment failed', [
            'checkout_request_id' => $parsedCallback['checkout_request_id'],
            'result_desc' => $parsedCallback['result_desc'],
            'result_code' => $parsedCallback['result_code']
        ]);

        // Handle failed payment
        processFailedPayment($parsedCallback);
    }

    // Send success response to M-Pesa
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Accepted'
    ]);

} catch (ValidationException $e) {
    $logger->error('STK Push callback validation error', ['error' => $e->getMessage()]);

    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Validation failed: ' . $e->getMessage()
    ]);

} catch (Exception $e) {
    $logger->error('STK Push callback processing error', ['error' => $e->getMessage()]);

    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Processing failed: ' . $e->getMessage()
    ]);
}

/**
 * Process successful payment
 */
function processSuccessfulPayment(array $callbackData): void
{
    // Example: Update order status in database
    /*
    $pdo = new PDO($dsn, $username, $password);

    $stmt = $pdo->prepare("
        UPDATE orders
        SET status = 'paid',
            mpesa_receipt = :receipt,
            transaction_date = :date,
            updated_at = NOW()
        WHERE checkout_request_id = :checkout_id
    ");

    $stmt->execute([
        'receipt' => $callbackData['mpesa_receipt_number'],
        'date' => $callbackData['transaction_date'],
        'checkout_id' => $callbackData['checkout_request_id']
    ]);
    */

    // Example: Send confirmation email/SMS
    /*
    sendPaymentConfirmation([
        'phone_number' => $callbackData['phone_number'],
        'amount' => $callbackData['amount'],
        'receipt' => $callbackData['mpesa_receipt_number']
    ]);
    */

    // Example: Trigger webhook or notification
    /*
    triggerWebhook('payment.completed', [
        'checkout_request_id' => $callbackData['checkout_request_id'],
        'mpesa_receipt_number' => $callbackData['mpesa_receipt_number'],
        'amount' => $callbackData['amount'],
        'phone_number' => $callbackData['phone_number'],
        'transaction_date' => $callbackData['transaction_date']
    ]);
    */
}

/**
 * Process failed payment
 */
function processFailedPayment(array $callbackData): void
{
    // Example: Update order status
    /*
    $pdo = new PDO($dsn, $username, $password);

    $stmt = $pdo->prepare("
        UPDATE orders
        SET status = 'payment_failed',
            failure_reason = :reason,
            updated_at = NOW()
        WHERE checkout_request_id = :checkout_id
    ");

    $stmt->execute([
        'reason' => $callbackData['result_desc'],
        'checkout_id' => $callbackData['checkout_request_id']
    ]);
    */

    // Example: Send failure notification
    /*
    sendPaymentFailureNotification([
        'checkout_request_id' => $callbackData['checkout_request_id'],
        'reason' => $callbackData['result_desc']
    ]);
    */
}

/**
 * Example webhook trigger function
 */
function triggerWebhook(string $event, array $data): void
{
    // Implementation depends on your webhook system
    /*
    $webhook_url = 'https://your-app.com/webhook';

    $payload = [
        'event' => $event,
        'data' => $data,
        'timestamp' => time()
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    curl_close($ch);
    */
}