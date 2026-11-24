<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'midtrans' => [
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'snap_base_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js',
        'expiry_minutes' => 15,
        // Kanal yang ditampilkan di Snap sesuai metode pilihan pelanggan.
        // Aktifkan kanal terkait di dashboard Midtrans sebelum dipakai.
        'enabled_payments' => [
            'QRIS' => ['other_qris'],
            'BANK_TRANSFER' => [
                'bca_va',
                'bni_va',
                'bri_va',
                'permata_va',
                'echannel',
                'other_va',
            ],
        ],
    ],

    'rajaongkir' => [
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
        'api_key' => env('RAJAONGKIR_API_KEY'),
        'origin_id' => env('RAJAONGKIR_ORIGIN_ID'),
        'origin_label' => env('RAJAONGKIR_ORIGIN_LABEL'),
        'cache_ttl' => env('RAJAONGKIR_CACHE_TTL', 600),
    ],

];
