<?php

/**
 * M-Pesa SDK Laravel Service Provider
 * 
 * Laravel service provider for automatic SDK registration and configuration publishing.
 * 
 * @package MpesaSDK\Laravel
 * @author Abuti Martin <abutimartin778@gmail.com>
 * @version 1.0.0
 * @license MIT
 * @since 1.0.0
 */

namespace MpesaSDK\Laravel;

use Illuminate\Support\ServiceProvider;
use MpesaSDK\MpesaSDK;
use MpesaSDK\Config\Config;
use MpesaSDK\Utils\Logger;

class MpesaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/mpesa.php', 'mpesa');

        $this->app->singleton('mpesa', function ($app) {
            $config = $app['config']['mpesa'];
            
            $logger = $config['logging']['enabled'] 
                ? new Logger($config['logging']['level'], storage_path('logs/mpesa.log'))
                : null;

            return new MpesaSDK($config, null, $logger);
        });

        $this->app->alias('mpesa', MpesaSDK::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/mpesa.php' => config_path('mpesa.php'),
            ], 'mpesa-config');
        }
    }

    public function provides()
    {
        return ['mpesa', MpesaSDK::class];
    }
}