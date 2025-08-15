<?php

/**
 * M-Pesa SDK Laravel Facade
 * 
 * Laravel facade for easy access to M-Pesa SDK functionality.
 * 
 * @package MpesaSDK\Laravel
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Laravel;

use Illuminate\Support\Facades\Facade;

class MpesaFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mpesa';
    }
}