<?php

return [

    // URL publique de votre gateway (Cloudflare Tunnel)
    'gateway_url' => env('SMS_GATEWAY_URL', ''),

    // Identifiants Basic Auth de l'app Android SMS Gateway
    'gateway_user' => env('SMS_GATEWAY_USER', 'admin'),
    'gateway_pass' => env('SMS_GATEWAY_PASS', ''),

    // Votre numéro émetteur (affiché comme expéditeur)
    'sender_phone' => env('SMS_SENDER_PHONE', '+2290199955078'),

    // Activer/désactiver globalement les SMS
    'enabled' => env('SMS_ENABLED', true),
];