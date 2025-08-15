<?php

/**
 * Custom API Manager - Direct equivalent of your Flask ApiManager
 *
 * This class replicates your Flask application's logic exactly
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;

class ApiManager
{
    private $database;
    private $logger;

    public function __construct($database = null, $logger = null)
    {
        $this->database = $database;
        $this->logger = $logger ?: new Logger(Logger::LEVEL_INFO, __DIR__ . '/logs/mpesa.log');
    }

    /**
     * Factory method - equivalent to ApiManager.self_processes() in Flask
     */
    public static function self_processes($database = null): self
    {
        return new self($database);
    }

    /**
     * Store transaction - equivalent to your api.store_transaction(data)
     */
    public function store_transaction(array $data): bool
    {
        try {
            // Your database storage logic here
            if ($this->database) {
                $stmt = $this->database->prepare("
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
            }

            // Fallback: log to file if no database
            $this->log("Transaction stored: " . json_encode($data), [], 'info');
            return true;

        } catch (Exception $e) {
            $this->log("Failed to store transaction: " . $e->getMessage(), ['data' => $data], 'error');
            return false;
        }
    }

    /**
     * Log method - equivalent to your D.log() function
     */
    public function log(string $message, array $context = [], string $level = 'info', bool $from_url = false, bool $trace = false): void
    {
        $logData = [
            'message' => $message,
            'context' => $context,
            'from_url' => $from_url,
            'trace' => $trace
        ];

        if ($trace) {
            $logData['stack_trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        if ($this->logger) {
            $this->logger->log($level, $message, $logData);
        }
    }

    /**
     * Main callback handler - Direct equivalent of your Flask route
     */
    public function mpesa_callback(): array
    {
        try {
            // Get callback data (equivalent to req.json in Flask)
            $input = file_get_contents('php://input');
            $requestData = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in request');
            }

            // Parse data exactly like your Flask code
            $data = is_string($requestData) ? json_decode($requestData, true) : $requestData;
            $callback = $data['Body']['stkCallback'] ?? [];
            $result_code = $callback['ResultCode'] ?? null;
            $result_desc = $callback['ResultDesc'] ?? '';
            $merchant_request_id = $callback['MerchantRequestID'] ?? '';
            $checkout_request_id = $callback['CheckoutRequestID'] ?? '';

            // Get API manager instance (equivalent to ApiManager.self_processes())
            $api = self::self_processes($this->database);

            if ($result_code == 0) {
                // Success - extract callback metadata
                $callback_metadata = $callback['CallbackMetadata']['Item'] ?? [];

                $amount = null;
                $mpesa_receipt_number = null;
                $transaction_date = null;
                $phone_number = null;

                // Extract values exactly like your Flask code
                foreach ($callback_metadata as $item) {
                    switch ($item['Name']) {
                        case 'Amount':
                            $amount = $item['Value'] ?? null;
                            break;
                        case 'MpesaReceiptNumber':
                            $mpesa_receipt_number = $item['Value'] ?? null;
                            break;
                        case 'TransactionDate':
                            $transaction_date = $item['Value'] ?? null;
                            break;
                        case 'PhoneNumber':
                            $phone_number = $item['Value'] ?? null;
                            break;
                    }
                }

                // Prepare success data exactly like Flask
                $transactionData = [
                    "merchant_request_id" => $merchant_request_id,
                    "checkout_request_id" => $checkout_request_id,
                    "amount" => $amount,
                    "mpesa_receipt_number" => $mpesa_receipt_number,
                    "transaction_date" => $transaction_date,
                    "status" => 'success',
                    "phone_number" => $phone_number
                ];

                // Store transaction
                $api->store_transaction($transactionData);

                // Log exactly like Flask D.log
                $api->log(
                    "Payment successful. Receipt: {$mpesa_receipt_number}, Amount: {$amount}, Phone: {$phone_number}",
                    [],
                    'info',
                    true
                );

            } else {
                // Failure - prepare failure data exactly like Flask
                $transactionData = [
                    "merchant_request_id" => $merchant_request_id,
                    "checkout_request_id" => $checkout_request_id,
                    "amount" => "",
                    "mpesa_receipt_number" => "",
                    "transaction_date" => "",
                    "status" => 'failed',
                    "phone_number" => ""
                ];

                // Store failed transaction
                $api->store_transaction($transactionData);

                // Log failure exactly like Flask D.log with trace
                $api->log(
                    "Payment failed. Reason: {$result_desc}",
                    [],
                    'warning',
                    true,
                    true
                );
            }

            // Return success response exactly like Flask
            return ["status" => "success"];

        } catch (Exception $e) {
            $this->log("Callback processing error: " . $e->getMessage(), [], 'error', true, true);
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}

// Usage as a direct callback endpoint (equivalent to your Flask route)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize with database connection
    $dsn = "mysql:host=localhost;dbname=your_database";
    $username = "your_username";
    $password = "your_password";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $apiManager = new ApiManager($pdo);
        $response = $apiManager->mpesa_callback();

        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode($response);

    } catch (PDOException $e) {
        $apiManager = new ApiManager(); // Without database
        $apiManager->log("Database connection failed: " . $e->getMessage(), [], 'error');

        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error"]);
    }
}