<?php

return [
    'aws' => [
        'version' => 'latest',
    ],
    'db' => [
        'driver' => 'mysql', // or 'pgsql'
        'host' => 'localhost',
        'port'      => 3306,   
        'database' => 'aws_spot',
        'username' => 'root',
        'password' => 'mysqlpw',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],
    'fetch-spot-data' => [
        // Control which functions should run, leave all true if you dont know what you're doing. Investigate fetch-spot-data.php file.
        'run_awsspotpricing'           => true,
        'run_createLatestPricesTable'  => true,
        'run_createStealSpotPricingTable' => true,

        // Control the start and end time of fetching availabe spot data from aws. insert null to get furthest/latests. 
        'StartTime' => '-1 day',
        'EndTime' => 'now'
    ]
];
