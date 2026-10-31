<?php

return [
    'paypal' => [
        'environment' => env('PAYPAL_ENV', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'exchange_rate' => [
        'api_url' => env('EXCHANGE_RATE_API_URL', 'https://open.er-api.com/v6/latest/USD'),
        'source' => env('EXCHANGE_RATE_SOURCE', 'exchange-rate-api-open-access'),
        'rate_path' => env('EXCHANGE_RATE_RATE_PATH', 'rates.NIO'),
        'date_path' => env('EXCHANGE_RATE_DATE_PATH', 'date'),
        'base_path' => env('EXCHANGE_RATE_BASE_PATH', 'base'),
        'expected_base' => env('EXCHANGE_RATE_EXPECTED_BASE', 'USD'),
        'timeout' => env('EXCHANGE_RATE_TIMEOUT', 6),
        'api_is_official' => env('EXCHANGE_RATE_API_IS_OFFICIAL', false),
        'max_age_days' => env('EXCHANGE_RATE_MAX_AGE_DAYS', 3),
        // Los fallbacks no tienen valores financieros predeterminados. Si se
        // configuran, la fecha es obligatoria y determina su vigencia real.
        'manual_usd_nio' => env('EXCHANGE_RATE_MANUAL_USD_NIO'),
        'manual_usd_nio_date' => env('EXCHANGE_RATE_MANUAL_USD_NIO_DATE'),
        'manual_eur_nio' => env('EXCHANGE_RATE_MANUAL_EUR_NIO'),
        'manual_eur_nio_date' => env('EXCHANGE_RATE_MANUAL_EUR_NIO_DATE'),
        'manual_inr_nio' => env('EXCHANGE_RATE_MANUAL_INR_NIO'),
        'manual_inr_nio_date' => env('EXCHANGE_RATE_MANUAL_INR_NIO_DATE'),
        'default_markup' => env('CLIENT_OFFER_DEFAULT_MARKUP', 1.35),
    ],

    'storefront' => [
        'catalog_currency' => env('STOREFRONT_CATALOG_CURRENCY', 'NIO'),
        'free_shipping_threshold_usd' => env('STOREFRONT_FREE_SHIPPING_THRESHOLD_USD', '250.00'),
        'whatsapp_owner' => env('STOREFRONT_WHATSAPP_OWNER', env('WHATSAPP_OWNER')),
    ],

];
