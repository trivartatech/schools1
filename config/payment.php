<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */
    'default' => env('PAYMENT_GATEWAY', 'razorpay'),

    /*
    |--------------------------------------------------------------------------
    | Razorpay Configuration
    |--------------------------------------------------------------------------
    */
    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID', ''),
        'key_secret' => env('RAZORPAY_KEY_SECRET', ''),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
    ],

];
