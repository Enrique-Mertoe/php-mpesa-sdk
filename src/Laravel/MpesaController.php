<?php

/**
 * M-Pesa SDK Laravel Controller
 * 
 * Example Laravel controller for M-Pesa integration.
 * 
 * @package MpesaSDK\Laravel
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Laravel;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use MpesaSDK\MpesaSDK;
use MpesaSDK\Callbacks\STKPushCallback;
use MpesaSDK\Utils\Logger;

class MpesaController extends Controller
{
    protected $mpesa;

    public function __construct(MpesaSDK $mpesa)
    {
        $this->mpesa = $mpesa;
    }

    public function stkPush(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'account_reference' => 'required|string',
            'description' => 'required|string',
        ]);

        $response = $this->mpesa->stkPush()->push(
            phoneNumber: $request->phone_number,
            amount: $request->amount,
            accountReference: $request->account_reference,
            transactionDescription: $request->description,
            callbackUrl: route('mpesa.callback')
        );

        return response()->json($response->getData());
    }

    public function callback(Request $request): JsonResponse
    {
        $callback = new STKPushCallback(new Logger());
        
        $callback->onSuccess(function($data) {
            \Log::info('Payment successful', $data);
        });
        
        $callback->onFailure(function($data) {
            \Log::warning('Payment failed', $data);
        });

        $response = $callback->process();
        return response()->json($response);
    }
}