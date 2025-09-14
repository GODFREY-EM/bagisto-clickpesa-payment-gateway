<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ClickPesa Hosted Integration Configuration
    |--------------------------------------------------------------------------
    | These settings control how your app connects to ClickPesa's system.
    */

    // Whether to use sandbox or live mode
    'environment' => env('CLICKPESA_ENV', 'sandbox'), // 'production' or 'sandbox'

    // ClickPesa API base URL
    'api_url' => env('CLICKPESA_API_URL', 'https://api.sandbox.clickpesa.com'),

    // Client ID provided by ClickPesa
    'client_id' => env('CLICKPESA_CLIENT_ID', ''),

    // API Secret or Key
    'api_secret' => env('CLICKPESA_API_SECRET', ''),

    // Checksum secret key
    'checksum_key' => env('CLICKPESA_CHECKSUM_KEY', ''),

    // Redirect URL after payment
    'return_url' => env('CLICKPESA_RETURN_URL', env('APP_URL') . '/clickpesa/return'),

    // Cancelled payment redirect
    'cancel_url' => env('CLICKPESA_CANCEL_URL', env('APP_URL') . '/clickpesa/cancel'),

    // Callback for payment notification
    'callback_url' => env('CLICKPESA_CALLBACK_URL', env('APP_URL') . '/clickpesa/callback'),

    // For display or metadata
    'merchant_id'   => env('CLICKPESA_MERCHANT_ID', ''),
    'merchant_name' => env('CLICKPESA_MERCHANT_NAME', config('app.name', 'Your Store')),

    //Order status codes
    'pending_order_expiry' => env('CLICKPESA_PENDING_ORDER_EXPIRY', 24),
];
