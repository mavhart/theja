<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    */
    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [

        /*
         * Soketi — server Pusher-compatibile self-hosted.
         * Le variabili PUSHER_* puntano al container Soketi locale.
         */
        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                'cluster'    => env('PUSHER_APP_CLUSTER', 'mt1'),
                'host'       => env('PUSHER_HOST', '127.0.0.1'),
                'port'       => env('PUSHER_PORT', 6001),
                'scheme'     => env('PUSHER_SCHEME', 'http'),
                'useTLS'     => env('PUSHER_SCHEME', 'http') === 'https',
                'curl_options' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ],
            ],
            'client_options' => [
                // Guzzle options per ambienti di sviluppo
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key'    => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
