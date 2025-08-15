<?php

/**
 * M-Pesa SDK Standalone Callback Handler
 * 
 * Standalone STK Push callback handler that can be used as a direct replacement
 * for Flask routes. Place this file on your server and set it as your callback URL.
 * 
 * @package MpesaSDK\Examples\Callbacks
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;

// Initialize logger
$logger = new Logger(Logger::LEVEL_INFO, __DIR__ . '/../logs/callback.log');

// Set response headers
header('Content-Type: application/json');

try {
    // Create callback processor with custom handlers
    $callback = STKPushCallback::createProcessor($logger);

    // Add custom success handler (equivalent to your Flask success logic)
    $callback->onSuccess(function($data) use ($logger) {
        // Store successful transaction
        storeTransaction([
            "merchant_request_id" => $data['merchant_request_id'],
            "checkout_request_id" => $data['checkout_request_id'],
            "amount" => $data['amount'],
            "mpesa_receipt_number" => $data['mpesa_receipt_number'],
            "transaction_date" => $data['transaction_date'],
            "status" => 'success',
            "phone_number" => $data['phone_number']
        ]);

        // Log success message (equivalent to your D.log)
        $logger->info("Payment successful. Receipt: {$data['mpesa_receipt_number']}, Amount: {$data['amount']}, Phone: {$data['phone_number']}");

        // Additional processing (send SMS, email, webhook, etc.)
        sendPaymentConfirmation($data);
    });

    // Add custom failure handler
    $callback->onFailure(function($data) use ($logger) {
        // Store failed transaction
        storeTransaction([
            "merchant_request_id" => $data['merchant_request_id'],
            "checkout_request_id" => $data['checkout_request_id'],
            "amount" => "",
            "mpesa_receipt_number" => "",
            "transaction_date" => "",
            "status" => 'failed',
            "phone_number" => ""
        ]);

        // Log failure message (equivalent to your D.log with trace)
        $logger->warning("Payment failed. Reason: {$data['result_desc']}");

        // Additional failure processing
        handlePaymentFailure($data);
    });

    // Process the callback
    $response = $callback->process();

    // Send response to M-Pesa
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $logger->error('Callback processing failed', ['error' => $e->getMessage()]);

    http_response_code(500);
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Internal server error'
    ]);
}

/**
 * Store transaction in database (implement your database logic here)
 */
function storeTransaction(array $data): void
{
    // Example PDO implementation
    /*
    try {
        $pdo = new PDO($dsn, $username, $password);

        $stmt = $pdo->prepare("
            INSERT INTO mpesa_transactions
            (merchant_request_id, checkout_request_id, amount, mpesa_receipt_number,
             transaction_date, status, phone_number, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $data['merchant_request_id'],
            $data['checkout_request_id'],
            $data['amount'],
            $data['mpesa_receipt_number'],
            $data['transaction_date'],
            $data['status'],
            $data['phone_number']
        ]);

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
    */

    // For now, just log the data
    error_log("Transaction data: " . json_encode($data));
}

/**
 * Send payment confirmation (SMS, email, etc.)
 */
function sendPaymentConfirmation(array $data): void
{
    // Implement your notification logic here
    // Examples:
    // - Send SMS confirmation
    // - Send email receipt
    // - Trigger webhook
    // - Update order status

    // Example webhook trigger
    /*
    $webhookUrl = 'https://your-app.com/webhook/payment-success';

    $payload = [
        'event' => 'payment.completed',
        'data' => $data,
        'timestamp' => time()
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Webhook failed: HTTP $httpCode - $response");
    }
    */
}

/**
 * Handle payment failure
 */
function handlePaymentFailure(array $data): void
{
    // Implement failure handling logic
    // Examples:
    // - Send failure notification
    // - Retry payment logic
    // - Update order status to failed
    // - Log for manual review

    // Example: Send failure notification
    /*
    $message = "Payment failed for checkout ID: {$data['checkout_request_id']}. Reason: {$data['result_desc']}";

    // Send to admin email/Slack/etc.
    mail('admin@yoursite.com', 'M-Pesa Payment Failed', $message);
    */
}