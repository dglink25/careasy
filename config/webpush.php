<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Keys
    |--------------------------------------------------------------------------
    |
    | Les clés VAPID (Voluntary Application Server Identification) sont utilisées
    | pour authentifier votre serveur lors de l'envoi de notifications push.
    |
    */
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@localhost'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Cloud Messaging (Optionnel)
    |--------------------------------------------------------------------------
    |
    | Si vous voulez supporter GCM (anciennement pour Chrome), vous pouvez
    | configurer ces clés. Ce n'est généralement plus nécessaire aujourd'hui.
    |
    */
    'gcm' => [
        'key' => env('GCM_KEY', ''),
        'sender_id' => env('GCM_SENDER_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Options par défaut
    |--------------------------------------------------------------------------
    */
    'default_options' => [
        'TTL' => 86400, // 24 heures en secondes
        'urgency' => 'normal', // 'very-low', 'low', 'normal', 'high'
        'topic' => null,
    ],
];