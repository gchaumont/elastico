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
            // 'Handler'
            // 'selector' => RoundRobinSelector::class,
            // 'connectionPool' => StaticNoPingConnectionPool::class,
        ],
    ],
];
