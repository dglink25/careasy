<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FedaPay API Configuration
    |--------------------------------------------------------------------------
    */

    // La clé secrète est utilisée pour l'authentification API (Bearer token)
    'secret_key' => env('FEDAPAY_SECRET_KEY'),
    
    // La clé publique est utilisée côté client (JavaScript)
    'public_key' => env('FEDAPAY_PUBLIC_KEY'),
    
    // Environnement : sandbox ou live
    'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
    
    // Webhook secret pour vérifier les callbacks
    'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
    
    // URLs de l'API
    'urls' => [
        'sandbox' => 'https://sandbox-api.fedapay.com/v1',
        'live' => 'https://api.fedapay.com/v1',
    ],
    
    // Configuration par défaut
    'currency' => env('FEDAPAY_CURRENCY', 'XOF'),
    'locale' => 'fr',
    
    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    */
    'callback_url' => env('APP_URL') . '/api/paiements/callback',
    'return_url' => env('APP_URL') . '/paiement/success',
    'cancel_url' => env('APP_URL') . '/paiement/cancel',
];