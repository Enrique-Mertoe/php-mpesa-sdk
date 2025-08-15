<?php

/**
 * SDK Integration Example
 *
 * This shows how to integrate the callback handling directly into the main SDK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MpesaSDK\MpesaSDK;
use MpesaSDK\Utils\Logger;

// Database setup
$dsn = "mysql:host=localhost;dbname=your_database";
$username = "your_username";
$password = "your_password";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Initialize M-Pesa SDK
$mpesa = MpesaSDK::sandbox([
    'consumer_key' => 'your_consumer_key',
    'consumer_secret' => 'your_consumer_secret',
    'business_short_code' => '174379',
    'passkey' => 'your_passkey'
]);

// Create callback processor with SDK integration
$callbackProcessor = $mpesa->stkPush();

/**
 * Process M-Pesa callback using SDK validation
 */
function processCallback($pdo, $mpesa): array
{
    try {
        // Get and validate callback data using SDK
        $input = file_get_contents('php://input');
        $callbackData = json_decode($input, true);

        if (empty($callbackData)) {
            throw new Exception('Empty callback data');
        }

        // Use SDK's validation method
        $parsedCallback = $mpesa->stkPush()->validateCallback($callbackData);

        // Process exactly like your Flask route
        if ($parsedCallback['is_successful']) {
            // Success case
            $transactionData = [
                "merchant_request_id" => $parsedCallback['merchant_request_id'],
                "checkout_request_id" => $parsedCallback['checkout_request_id'],
                "amount" => $parsedCallback['amount'],
                "mpesa_receipt_number" => $parsedCallback['mpesa_receipt_number'],
                "transaction_date" => $parsedCallback['transaction_date'],
                "status" => 'success',
                "phone_number" => $parsedCallback['phone_number']
            ];

            // Store in database (your api.store_transaction equivalent)
            storeTransaction($pdo, $transactionData);

            // Log success (your D.log equivalent)
            logMessage(
                "Payment successful. Receipt: {$parsedCallback['mpesa_receipt_number']}, " .
                "Amount: {$parsedCallback['amount']}, Phone: {$parsedCallback['phone_number']}",
                'info',
                true
            );

        } else {
            // Failure case
            $transactionData = [
                "merchant_request_id" => $parsedCallback['merchant_request_id'],
                "checkout_request_id" => $parsedCallback['checkout_request_id'],
                "amount" => "",
                "mpesa_receipt_number" => "",
                "transaction_date" => "",
                "status" => 'failed',
                "phone_number" => ""
            ];

            // Store failed transaction
            storeTransaction($pdo, $transactionData);

            // Log failure with trace
            logMessage(
                "Payment failed. Reason: {$parsedCallback['result_desc']}",
                'warning',
                true,
                true
            );
        }

        return ["status" => "success"];

    } catch (Exception $e) {
        logMessage("Callback error: " . $e->getMessage(), 'error', true, true);
        return ["status" => "error", "message" => $e->getMessage()];
    }
}

/**
 * Store transaction - equivalent to your api.store_transaction
 */
function storeTransaction($pdo, array $data): bool
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mpesa_transactions 
            (merchant_request_id, checkout_request_id, amount, mpesa_receipt_number, 
             transaction_date, status, phone_number, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            mpesa_receipt_number = VALUES(mpesa_receipt_number),
            transaction_date = VALUES(transaction_date),
            status = VALUES(status),
            phone_number = VALUES(phone_number),
            updated_at = NOW()
        ");

        return $stmt->execute([
            $data['merchant_request_id'],
            $data['checkout_request_id'],
            $data['amount'],
            $data['mpesa_receipt_number'],
            $data['transaction_date'],
            $data['status'],
            $data['phone_number']
        ]);

    } catch (PDOException $e) {
        logMessage("Database error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Log message - equivalent to your D.log function
 */
function logMessage(string $message, string $level = 'info', bool $fromUrl = false, bool $trace = false): void
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'from_url' => $fromUrl
    ];

    if ($trace) {
        $logEntry['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    // Log to file
    error_log(json_encode($logEntry) . PHP_EOL, 3, __DIR__ . '/logs/mpesa_callback.log');

    // Also log to error log for immediate visibility
    error_log("M-Pesa [{$level}]: {$message}");
}

// Main execution - process the callback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = processCallback($pdo, $mpesa);

    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode($response);
    exit;
}

// If not POST request, show usage info
?>
<!DOCTYPE html>
<html>
<head>
    <title>M-Pesa Callback Endpoint</title>
</head>
<body>
<h1>M-Pesa Callback Endpoint</h1>
<p>This endpoint processes M-Pesa STK Push callbacks.</p>

<h2>Setup Instructions:</h2>
<ol>
    <li>Set this URL as your M-Pesa callback URL</li>
    <li>Ensure your database table exists:
        <pre><code>
CREATE TABLE mpesa_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_request_id VARCHAR(100),
    checkout_request_id VARCHAR(100) UNIQUE,
    amount DECIMAL(10,2),
    mpesa_receipt_number VARCHAR(50),
    transaction_date VARCHAR(50),
    status ENUM('success', 'failed'),
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
            </code></pre>
    </li>
    <li>Update database credentials in this file</li>
    <li>Ensure logs directory is writable</li>
</ol>

<h2>SDK Features Used:</h2>
<ul>
    <li>✅ Built-in callback validation</li>
    <li>✅ Error handling and logging</li>
    <li>✅ Data parsing and extraction</li>
    <li>✅ Environment management</li>
</ul>

<h2>Testing:</h2>
<p>You can test this endpoint by making a POST request with M-Pesa callback data structure.</p>
</body>
</html>