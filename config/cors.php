<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    | Autorise le frontend React (localhost:5173 ou 3000) à appeler l'API
    | Laravel (localhost:8000). Sans ça, le navigateur bloque toutes les
    | requêtes cross-origin → "Network Error" dans Axios.
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'broadcasting/auth',   // ← obligatoire pour Pusher
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',   // Vite (React)
        'http://localhost:3000',   // Create React App
        'http://localhost:5174',   // Vite port alternatif
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
         'https://careasy-frontend.vercel.app',
    ],

    'allowed_origins_patterns' => [
        '#^https://careasy-frontend(-[a-z0-9]+)?\.vercel\.app$#',  // ← sous-domaines Vercel preview
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | ⚠️  OBLIGATOIRE pour Sanctum (cookies de session + Authorization header)
    */
    'supports_credentials' => true,

];