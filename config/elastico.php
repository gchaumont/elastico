<?php

return [
    // The default connection name
    'default' => 'main',

    // The list of ElasticSearch connections
    'connections' => [
        'main' => [
            'async' => true,
            'username' => 'elastic',
            'password' => '*****',
            // 'cloud' => 'cloud_id',
            'hosts' => [
                'localhost:9200',
            ],
            'certificate' => storage_path('/certificate.crt'),
            // 'client' => [/*custom client configuration*/],
        ],
    ],
];
