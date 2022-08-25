<?php

return [
    // The default connection name
    'default' => 'elastic',

    // The list of ElasticSearch connections
    'connections' => [
        'elastic' => [
            'basicAuthentication' => [
                'username' => 'elastic',
                'password' => 'password',
            ],
            'hosts' => ['localhost:9200'],
            //'CABundle' => storage_path('/elastic/certificate.crt'),
        ],
    ],
];
