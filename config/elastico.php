<?php

return [
    // The default connection name
    'default' => 'default',

    // The list of elasticsearch connections
    'connections' => [
        'default' => [
            'BasicAuthentication' => [
                'username' => 'elastic',
                'password' => null,
            ],
            'Hosts' => ['localhost:9200'],
            // 'Handler'
            'Selector' => RoundRobinSelector::class,
            'ConnectionPool' => StaticNoPingConnectionPool::class,
        ],
    ],
];
