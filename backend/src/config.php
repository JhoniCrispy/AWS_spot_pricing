<?php

return [
    'aws' => [
        'version' => 'latest',
    ],
    'db' => [
        'driver' => 'mysql', // or 'pgsql'
        'host' => 'localhost',
        'port'      => 3306,   
        'database' => 'spot_prices',
        'username' => 'root',
        'password' => 'mysqlpw',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ]
];
