<?php

return [
    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'dsumeoiga'),
        'api_key'    => env('CLOUDINARY_API_KEY', '571431578845174'),
        'api_secret' => env('CLOUDINARY_API_SECRET', 'JUkkERciRqqYAset1e3XBuCuzuE'),
    ],
    'url' => [
        'secure' => true
    ],
    'notification_url' => null,
    'upload_preset' => null,
    'upload_route' => null,
    'upload_action' => null,
];