<?php

/**
 * Framework Integration Examples
 *
 * This file shows how to integrate the callback handler with different PHP frameworks
 */

// ================================
// LARAVEL INTEGRATION
// ================================

/*
// In your Laravel routes/web.php or routes/api.php
Route::post('/mpesa/callback', [MpesaController::class, 'stkPushCallback']);

// In app/Http/Controllers/MpesaController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    public function stkPushCallback(Request $request): JsonResponse
    {
        try {
            // Create logger that uses Laravel's logging system
            $logger = new Logger(Logger::LEVEL_INFO);

            // Create callback processor
            $callback = new STKPushCallback($logger);

            // Set success handler
            $callback->onSuccess(function($data) {
                // Use Laravel Eloquent to store transaction
                Transaction::create([
                    'merchant_request_id' => $data['merchant_request_id'],
                    'checkout_request_id' => $data['checkout_request_id'],
                    'amount' => $data['amount'],
                    'mpesa_receipt_number' => $data['mpesa_receipt_number'],
                    'transaction_date' => $data['transaction_date'],
                    'status' => 'success',
                    'phone_number' => $data['phone_number']
                ]);

                // Log using Laravel's logger
                Log::info("Payment successful", $data);

                // Dispatch job for additional processing
                // ProcessPaymentSuccess::dispatch($data);
            });

            // Set failure handler
            $callback->onFailure(function($data) {
                Transaction::create([
                    'merchant_request_id' => $data['merchant_request_id'],
                    'checkout_request_id' => $data['checkout_request_id'],
                    'status' => 'failed',
                    'failure_reason' => $data['result_desc']
                ]);

                Log::warning("Payment failed", $data);
            });

            // Process callback using request data directly
            $callbackData = $request->all();
            $parsedCallback = $callback->validateCallback($callbackData);

            if ($parsedCallback['is_successful']) {
                $callback->onSuccess($parsedCallback);
            } else {
                $callback->onFailure($parsedCallback);
            }

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

        } catch (\Exception $e) {
            Log::error('M-Pesa callback error: ' . $e->getMessage());
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Failed'], 500);
        }
    }
}
*/

// ================================
// CODEIGNITER 4 INTEGRATION
// ================================

/*
// In app/Controllers/Mpesa.php
<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;

class Mpesa extends ResourceController
{
    public function stkPushCallback()
    {
        try {
            $logger = new Logger(Logger::LEVEL_INFO, WRITEPATH . 'logs/mpesa.log');
            $callback = new STKPushCallback($logger);

            // Set handlers
            $callback->onSuccess(function($data) {
                // Use CodeIgniter's model
                $transactionModel = new \App\Models\TransactionModel();
                $transactionModel->insert([
                    'merchant_request_id' => $data['merchant_request_id'],
                    'checkout_request_id' => $data['checkout_request_id'],
                    'amount' => $data['amount'],
                    'mpesa_receipt_number' => $data['mpesa_receipt_number'],
                    'transaction_date' => $data['transaction_date'],
                    'status' => 'success',
                    'phone_number' => $data['phone_number']
                ]);

                log_message('info', 'Payment successful: ' . json_encode($data));
            });

            $callback->onFailure(function($data) {
                $transactionModel = new \App\Models\TransactionModel();
                $transactionModel->insert([
                    'merchant_request_id' => $data['merchant_request_id'],
                    'checkout_request_id' => $data['checkout_request_id'],
                    'status' => 'failed',
                    'failure_reason' => $data['result_desc']
                ]);

                log_message('warning', 'Payment failed: ' . json_encode($data));
            });

            $response = $callback->process();
            return $this->response->setJSON($response);

        } catch (\Exception $e) {
            log_message('error', 'M-Pesa callback error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'ResultCode' => 1,
                'ResultDesc' => 'Failed'
            ]);
        }
    }
}
*/

// ================================
// SYMFONY INTEGRATION
// ================================

/*
// In src/Controller/MpesaController.php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MpesaController extends AbstractController
{
    #[Route('/mpesa/callback', methods: ['POST'])]
    public function stkPushCallback(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $mpesaLogger = new Logger(Logger::LEVEL_INFO);
            $callback = new STKPushCallback($mpesaLogger);

            // Set success handler with Doctrine
            $callback->onSuccess(function($data) use ($em, $logger) {
                $transaction = new Transaction();
                $transaction->setMerchantRequestId($data['merchant_request_id']);
                $transaction->setCheckoutRequestId($data['checkout_request_id']);
                $transaction->setAmount($data['amount']);
                $transaction->setMpesaReceiptNumber($data['mpesa_receipt_number']);
                $transaction->setTransactionDate(new \DateTime($data['transaction_date']));
                $transaction->setStatus('success');
                $transaction->setPhoneNumber($data['phone_number']);

                $em->persist($transaction);
                $em->flush();

                $logger->info('Payment successful', $data);
            });

            // Set failure handler
            $callback->onFailure(function($data) use ($em, $logger) {
                $transaction = new Transaction();
                $transaction->setMerchantRequestId($data['merchant_request_id']);
                $transaction->setCheckoutRequestId($data['checkout_request_id']);
                $transaction->setStatus('failed');
                $transaction->setFailureReason($data['result_desc']);

                $em->persist($transaction);
                $em->flush();

                $logger->warning('Payment failed', $data);
            });

            $response = $callback->process();
            return new JsonResponse($response);

        } catch (\Exception $e) {
            $logger->error('M-Pesa callback error: ' . $e->getMessage());
            return new JsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Failed'], 500);
        }
    }
}
*/

// ================================
// SLIM FRAMEWORK INTEGRATION
// ================================

/*
// In your Slim routes
use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/mpesa/callback', function (Request $request, Response $response) {
    try {
        $logger = new Logger(Logger::LEVEL_INFO, __DIR__ . '/../logs/mpesa.log');
        $callback = new STKPushCallback($logger);

        // Get PDO from container
        $pdo = $this->get('db');

        $callback->onSuccess(function($data) use ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO transactions
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
                'success',
                $data['phone_number']
            ]);
        });

        $callback->onFailure(function($data) use ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO transactions
                (merchant_request_id, checkout_request_id, status, failure_reason, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $data['merchant_request_id'],
                $data['checkout_request_id'],
                'failed',
                $data['result_desc']
            ]);
        });

        $callbackResponse = $callback->process();

        $response->getBody()->write(json_encode($callbackResponse));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $errorResponse = ['ResultCode' => 1, 'ResultDesc' => 'Failed'];
        $response->getBody()->write(json_encode($errorResponse));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
*/

// ================================
// VANILLA PHP WITH CLASS-BASED APPROACH
// ================================

class MpesaCallbackProcessor
{
    private $database;
    private $logger;

    public function __construct($database, $logger = null)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    public function processSTKPushCallback()
    {
        try {
            $callback = new STKPushCallback($this->logger);

            // Set custom handlers
            $callback->onSuccess([$this, 'handleSuccess']);
            $callback->onFailure([$this, 'handleFailure']);

            $response = $callback->process();

            header('Content-Type: application/json');
            echo json_encode($response);

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Callback error: ' . $e->getMessage());
            }

            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Failed']);
        }
    }

    public function handleSuccess(array $data): void
    {
        // Your success logic here - exactly like your Flask route
        $transactionData = [
            "merchant_request_id" => $data['merchant_request_id'],
            "checkout_request_id" => $data['checkout_request_id'],
            "amount" => $data['amount'],
            "mpesa_receipt_number" => $data['mpesa_receipt_number'],
            "transaction_date" => $data['transaction_date'],
            "status" => 'success',
            "phone_number" => $data['phone_number']
        ];

        $this->storeTransaction($transactionData);

        if ($this->logger) {
            $this->logger->info("Payment successful. Receipt: {$data['mpesa_receipt_number']}, Amount: {$data['amount']}, Phone: {$data['phone_number']}");
        }
    }

    public function handleFailure(array $data): void
    {
        // Your failure logic here
        $transactionData = [
            "merchant_request_id" => $data['merchant_request_id'],
            "checkout_request_id" => $data['checkout_request_id'],
            "amount" => "",
            "mpesa_receipt_number" => "",
            "transaction_date" => "",
            "status" => 'failed',
            "phone_number" => ""
        ];

        $this->storeTransaction($transactionData);

        if ($this->logger) {
            $this->logger->warning("Payment failed. Reason: {$data['result_desc']}");
        }
    }

    private function storeTransaction(array $data): void
    {
        // Implement your database storage logic
        // This is where you'd call your equivalent of api.store_transaction($data)
    }
}

// Usage:
/*
$database = new PDO($dsn, $username, $password);
$logger = new Logger(Logger::LEVEL_INFO, 'mpesa.log');
$processor = new MpesaCallbackProcessor($database, $logger);
$processor->processSTKPushCallback();
*/