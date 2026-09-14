<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Priority Groups
    |--------------------------------------------------------------------------
    |
    | When running `php artisan queue:work --queue=$QUEUE_HIGH,$QUEUE_DEFAULT,$QUEUE_LOW`
    | workers will drain high-priority queues before moving to lower ones.
    | Use these env values verbatim in Supervisor / Forge worker configs.
    |
    */

    'priorities' => [
        'high' => env('QUEUE_HIGH', 'payments,stripe'),
        'default' => env('QUEUE_DEFAULT', 'default,notifications'),
        'low' => env('QUEUE_LOW', 'analytics,reports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    /*
     * DEUX RÉGLAGES QUI NE SE VOIENT QU'EN PRODUCTION, et que la suite ne peut pas exercer : elle
     * tourne en `sync`, le développement en `database`, seule la production est en `redis`.
     *
     * `retry_after` DOIT DÉPASSER LE PLUS LONG `--timeout` DE WORKER de la connexion. Il valait 90
     * face à des workers en `--timeout=120` (et 600 pour l'antivirus) : tout job plus long était
     * remis en file et exécuté UNE SECONDE FOIS EN PARALLÈLE de la première — des SMS marketing
     * facturés en double, un appariement facial refacturé. 660 couvre le plus long des deux.
     *
     * `after_commit` à faux faisait partir un job enfilé DANS une transaction avant son commit : le
     * worker lisait une ligne qui n'existait pas encore, ou livrait un webhook que le rollback
     * annulait ensuite. Trois sites en dépendaient sans le savoir.
     */
    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 660,
            'after_commit' => true,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 660,
            'block_for' => 0,
            'after_commit' => true,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 660,
            'block_for' => null,
            'after_commit' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
