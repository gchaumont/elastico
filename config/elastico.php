<?php

return [
    // The default connection name
    'default' => 'elastic',

    // The list of elasticsearch connections
    'connections' => [
        'elastic' => [
            'basicAuthentication' => [
                'username' => 'elastic',
                'password' => 'password',
            ],
            'hosts' => ['localhost:9200'],
            //'CABundle' => storage_path('/elastic/certificate.crt'),

            // 'Handler'
            // 'selector' => RoundRobinSelector::class,
            // 'connectionPool' => StaticNoPingConnectionPool::class,
        ],
    ],

    'forwarding' => [
        'default' => [
            'env' => ['local'],
            // 'domain' => 'your-domain.dev',
            // 'middleware' => [],
            'path' => 'elastic-forwarding',
        ],
    ],
];
