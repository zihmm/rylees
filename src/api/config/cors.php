<?php

declare(strict_types=1);

return [

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://console.rylees.ai',
        'https://*.rylees.ai',
        'http://console.rylees.test',
        'http://*.rylees.test',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
