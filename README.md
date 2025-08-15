# M-Pesa SDK for PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)](https://php.net)

A comprehensive PHP SDK for integrating with Safaricom's M-Pesa API. This SDK provides a clean, modern interface for all M-Pesa services including STK Push, B2C, B2B, C2B, Account Balance, Transaction Status, and Reversal operations.

## Features

- ✅ **STK Push (Lipa Na M-Pesa Online)** - Customer payments
- ✅ **B2C (Business to Customer)** - Send money to customers
- ✅ **B2B (Business to Business)** - Business payments
- ✅ **C2B (Customer to Business)** - Register and simulate C2B
- ✅ **Account Balance** - Check account balance
- ✅ **Transaction Status** - Query transaction status
- ✅ **Reversal** - Reverse transactions
- ✅ **Callback Handling** - Built-in callback processors
- ✅ **Environment Management** - Sandbox and Production support
- ✅ **Comprehensive Logging** - Built-in logging system
- ✅ **Input Validation** - Automatic parameter validation
- ✅ **Error Handling** - Detailed error reporting
- ✅ **Framework Integration** - Laravel, Symfony, CodeIgniter support

## Requirements

- PHP 8.0 or higher
- cURL extension
- JSON extension
- OpenSSL extension

## Installation

Install via Composer:

```bash
composer require abutimartin/mpesa-sdk
```

### Laravel Integration

For Laravel applications, the service provider will be automatically registered. Publish the configuration:

```bash
php artisan vendor:publish --tag=mpesa-config
```

Add to your `.env` file:

```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_BUSINESS_SHORT_CODE=174379
MPESA_PASSKEY=your_passkey
MPESA_INITIATOR_NAME=your_initiator
MPESA_SECURITY_CREDENTIAL=your_security_credential
```

## Quick Start

### Basic STK Push Example

```php
<?php

require_once 'vendor/autoload.php';

use MpesaSDK\MpesaSDK;

// Initialize SDK for sandbox
$mpesa = MpesaSDK::sandbox([
    'consumer_key' => 'your_consumer_key',
    'consumer_secret' => 'your_consumer_secret',
    'business_short_code' => '174379',
    'passkey' => 'your_passkey'
]);

// Initiate STK Push
$response = $mpesa->stkPush()->push(
    phoneNumber: '254712345678',
    amount: 100.00,
    accountReference: 'ORDER123',
    transactionDescription: 'Payment',
    callbackUrl: 'https://yourapp.com/callback'
);

if ($response->isSuccessful()) {
    echo "Payment initiated! Checkout ID: " . $response->get('CheckoutRequestID');
} else {
    echo "Error: " . $response->getErrorMessage();
}
```

### Environment Configuration

#### Using Environment Variables

Create a `.env` file:

```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_BUSINESS_SHORT_CODE=174379
MPESA_PASSKEY=your_passkey
MPESA_INITIATOR_NAME=your_initiator
MPESA_SECURITY_CREDENTIAL=your_security_credential
```

Then initialize:

```php
$mpesa = MpesaSDK::fromEnv();
```

#### Direct Configuration

```php
// Sandbox
$mpesa = MpesaSDK::sandbox([
    'consumer_key' => 'your_consumer_key',
    'consumer_secret' => 'your_consumer_secret',
    'business_short_code' => '174379',
    'passkey' => 'your_passkey'
]);

// Production
$mpesa = MpesaSDK::production([
    'consumer_key' => 'your_consumer_key',
    'consumer_secret' => 'your_consumer_secret',
    'business_short_code' => 'your_shortcode',
    'passkey' => 'your_passkey',
    'initiator_name' => 'your_initiator',
    'security_credential' => 'your_security_credential'
]);
```

## Services

### STK Push (Lipa Na M-Pesa Online)

```php
// Basic STK Push
$response = $mpesa->stkPush()->push(
    phoneNumber: '254712345678',
    amount: 100.00,
    accountReference: 'ORDER123',
    transactionDescription: 'Payment',
    callbackUrl: 'https://yourapp.com/callback'
);

// Quick STK Push (minimal parameters)
$response = $mpesa->requestPayment(
    phoneNumber: '254712345678',
    amount: 100.00,
    callbackUrl: 'https://yourapp.com/callback'
);

// Query STK Push Status
$statusResponse = $mpesa->stkPush()->queryStatus($checkoutRequestId);
```

### B2C (Business to Customer)

```php
// Send money to customer
$response = $mpesa->b2c()->send(
    phoneNumber: '254712345678',
    amount: 100.00,
    commandId: 'BusinessPayment',
    remarks: 'Salary payment',
    queueTimeoutUrl: 'https://yourapp.com/timeout',
    resultUrl: 'https://yourapp.com/result'
);

// Quick send money
$response = $mpesa->sendMoney(
    phoneNumber: '254712345678',
    amount: 100.00,
    remarks: 'Payment',
    queueTimeoutUrl: 'https://yourapp.com/timeout',
    resultUrl: 'https://yourapp.com/result'
);
```

### Account Balance

```php
$response = $mpesa->accountBalance()->query(
    queueTimeoutUrl: 'https://yourapp.com/timeout',
    resultUrl: 'https://yourapp.com/result'
);
```

### Transaction Status

```php
$response = $mpesa->transactionStatus()->query(
    transactionId: 'LHG31AA5TX',
    queueTimeoutUrl: 'https://yourapp.com/timeout',
    resultUrl: 'https://yourapp.com/result'
);
```

## Callback Handling

### Simple Callback Handler

```php
<?php

require_once 'vendor/autoload.php';

use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;

$logger = new Logger(Logger::LEVEL_INFO, 'mpesa.log');
$callback = new STKPushCallback($logger);

// Set success handler
$callback->onSuccess(function($data) {
    // Store successful transaction
    echo "Payment successful: " . $data['mpesa_receipt_number'];
});

// Set failure handler
$callback->onFailure(function($data) {
    // Handle failed transaction
    echo "Payment failed: " . $data['result_desc'];
});

// Process callback
$response = $callback->process();

header('Content-Type: application/json');
echo json_encode($response);
```

### Framework Integration

#### Laravel

```php
// Using dependency injection
use MpesaSDK\MpesaSDK;

class PaymentController extends Controller
{
    public function initiatePayment(Request $request, MpesaSDK $mpesa)
    {
        $response = $mpesa->stkPush()->push(
            phoneNumber: $request->phone,
            amount: $request->amount,
            accountReference: $request->reference,
            transactionDescription: 'Payment',
            callbackUrl: route('mpesa.callback')
        );
        
        return response()->json($response->getData());
    }
    
    // Using facade
    public function quickPayment(Request $request)
    {
        $response = \Mpesa::requestPayment(
            phoneNumber: $request->phone,
            amount: $request->amount,
            callbackUrl: route('mpesa.callback')
        );
        
        return response()->json($response->getData());
    }
    
    public function handleCallback(Request $request)
    {
        $callback = new STKPushCallback();
        
        $callback->onSuccess(function($data) {
            Transaction::create([
                'checkout_request_id' => $data['checkout_request_id'],
                'mpesa_receipt_number' => $data['mpesa_receipt_number'],
                'amount' => $data['amount'],
                'status' => 'completed'
            ]);
        });
        
        $response = $callback->process();
        return response()->json($response);
    }
}
```

## Logging

The SDK includes comprehensive logging:

```php
use MpesaSDK\Utils\Logger;

// Create logger
$logger = new Logger(
    logLevel: Logger::LEVEL_INFO,
    logFile: 'mpesa.log',
    enableConsole: true
);

// Use with SDK
$mpesa = MpesaSDK::sandbox($config, null, $logger);

// Manual logging
$logger->info('Payment initiated', ['amount' => 100]);
$logger->error('Payment failed', ['error' => 'Invalid phone number']);
```

## Error Handling

```php
use MpesaSDK\Exceptions\MpesaException;
use MpesaSDK\Exceptions\ValidationException;

try {
    $response = $mpesa->stkPush()->push(
        phoneNumber: '254712345678',
        amount: 100.00,
        accountReference: 'ORDER123',
        transactionDescription: 'Payment',
        callbackUrl: 'https://yourapp.com/callback'
    );
    
    if (!$response->isSuccessful()) {
        throw new MpesaException($response->getErrorMessage());
    }
    
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage();
} catch (MpesaException $e) {
    echo "M-Pesa error: " . $e->getFullErrorMessage();
} catch (Exception $e) {
    echo "General error: " . $e->getMessage();
}
```

## Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

## Examples

The `examples/` directory contains comprehensive examples:

- `stkpush.php` - STK Push examples
- `callbacks/` - Callback handling examples
- `sdk_integration.php` - Full integration example
- `framework_callback.php` - Framework integration examples

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, email abutimartin778@gmail.com or create an issue on GitHub.

## Changelog

### v1.0.0
- Initial release
- STK Push support
- B2C support
- Callback handling
- Comprehensive logging
- Framework integration examples

## Author

**Abuti Martin**
- Email: abutimartin778@gmail.com
- GitHub: [@abutimartin](https://github.com/abutimartin)

---

Made with ❤️ for the PHP community