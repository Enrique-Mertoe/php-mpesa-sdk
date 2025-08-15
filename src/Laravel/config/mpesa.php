<?php

return [
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'business_short_code' => env('MPESA_BUSINESS_SHORT_CODE'),
    'passkey' => env('MPESA_PASSKEY'),
    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
    
    'logging' => [
        'enabled' => env('MPESA_LOGGING_ENABLED', true),
        'level' => env('MPESA_LOG_LEVEL', 'info'),
    ],
];