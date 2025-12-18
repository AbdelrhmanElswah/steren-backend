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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'odoo' => [
        'base_url' => env('ODOO_BASE_URL', 'https://odoo-clients.teleoceans.cloud'),
        'database' => env('ODOO_DATABASE', ''),
        'username' => env('ODOO_USERNAME', ''),
        'password' => env('ODOO_PASSWORD', ''),
        'api_key' => env('ODOO_API_KEY', ''),
    ],

    'dealer' => [
        // Auto-complete payment when dealer application is approved
        // Set to false to require manual payment completion
        'auto_complete_payment_on_approval' => env('DEALER_AUTO_COMPLETE_PAYMENT_ON_APPROVAL', true),
    ],

    'firebase' => [
        'enabled' => env('FIREBASE_ENABLED', false), // Set to true to enable Firebase push notifications
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'web_api_key' => env('FIREBASE_WEB_API_KEY'),
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID'),
        'credentials' => storage_path('app/firebase/firebase-credentials.json'),
        'scope_message_url' => env('FIREBASE_SCOPE_MESSAGE_URL', 'https://www.googleapis.com/auth/firebase.messaging'),
    ],

    'standingtech' => [
        'api_url' => env('STANDINGTECH_API_URL', 'https://gateway.standingtech.com/api/v4/sms/send'),
        'api_token' => env('STANDINGTECH_API_TOKEN'),
        'sender_id' => env('STANDINGTECH_SENDER_ID', null),
        'timeout' => env('STANDINGTECH_TIMEOUT', 30),
        'enabled' => env('STANDINGTECH_ENABLED', true),
    ],

    'vehicledatabases' => [
        'api_url' => env('VEHICLEDATABASES_API_URL', 'https://api.vehicledatabases.com/advanced-vin-decode/v2/{vin}'),
        'api_key' => env('VEHICLEDATABASES_API_KEY'),
        'timeout' => env('VEHICLEDATABASES_TIMEOUT', 30),
        'cache_expiry' => env('VEHICLEDATABASES_CACHE_EXPIRY', 24), // hours
        'enabled' => env('VEHICLEDATABASES_ENABLED', true),
        'ocr_endpoint' => env('VEHICLEDATABASES_OCR_ENDPOINT', 'https://api.vehicledatabases.com/vin-ocr'),
        'auction_endpoint' => env('VEHICLEDATABASES_AUCTION_ENDPOINT', 'https://api.vehicledatabases.com/auction'),
    ],
    
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
];
