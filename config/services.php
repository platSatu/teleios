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

    /*
    |--------------------------------------------------------------------------
    | Golang Backend (auth + WhatsApp bridge)
    |--------------------------------------------------------------------------
    |
    | Base URL and shared API key for the Go backend that handles JWT auth
    | and WhatsApp device connections (whatsmeow). The key must match
    | SECRET_API_KEY in the Go backend's own .env.
    |
    */

    'golang' => [
        'url' => env('GOLANG_API_URL', 'http://localhost:8080'),
        'key' => env('GOLANG_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Sign in / Sign up with Google)
    |--------------------------------------------------------------------------
    |
    | Powers App\Http\Controllers\Auth\AuthController::redirectToGoogle()/
    | handleGoogleCallback() via laravel/socialite. redirect must match,
    | character for character, an Authorized redirect URI configured on
    | the OAuth Client in Google Cloud Console.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile (captcha)
    |--------------------------------------------------------------------------
    |
    | Bot-spam protection on login/register/forgot-password — see
    | App\Rules\Turnstile (server-side verification against Cloudflare's
    | siteverify API) and the `cf-turnstile-response` field rendered by
    | the Turnstile JS widget in those three Blade forms. site_key is
    | public (goes in the HTML), secret_key must stay server-side only.
    |
    */

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Duitku (Payment Gateway Indonesia)
    |--------------------------------------------------------------------------
    |
    | Powers the wallet top-up flow — see App\Services\Payment\DuitkuService
    | and App\Http\Controllers\User\Deposit\DepositController. Uses the
    | "POP" integration (Duitku hosts the payment-method picker), via the
    | duitkupg/duitku-php SDK already required in composer.json.
    |
    */

    'duitku' => [
        'merchant_code' => env('DUITKU_MERCHANT_CODE'),
        'api_key' => env('DUITKU_API_KEY'),
        'sandbox' => env('DUITKU_SANDBOX', true),
        // How long Duitku itself keeps the invoice open once created
        // (sent as expiryPeriod in createInvoice).
        'expiry_minutes' => env('DUITKU_EXPIRY_MINUTES', 60),
        // Separate, shorter timer: how long our own checkout()
        // confirmation page waits for the user to press "Lanjutkan ke
        // Duitku" before auto-cancelling — see DepositController::
        // checkout()/cancelCheckout() and resources/views/user/deposit/
        // checkout.blade.php. This happens BEFORE any Duitku invoice
        // exists, so it's intentionally independent of expiry_minutes
        // above.
        'checkout_timeout_minutes' => env('DUITKU_CHECKOUT_TIMEOUT_MINUTES', 10),
    ],

];
