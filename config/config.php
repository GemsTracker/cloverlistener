<?php

require __DIR__ . '/db.inc.php';

return [
    /* Application configuration */
    'application' => [
        'ip'       => gethostbyname(gethostname()), // The ip address of this machine
        'port'     => 23887,                        // The port it will listen on
        'logfile'  => defined('LOG_LISTENER') ? LOG_DIR . '/' . LOG_LISTENER : null,
        ],
    /*  Database connector */
    'cleanup'       => [
        'logfile'  => defined('LOG_CLEANUP') ? LOG_DIR . '/' . LOG_CLEANUP : null,
    ],
    'database'    => [
        'driver'   => 'Mysqli',
        'database' => DATABASE,
        'username' => USER,
        'password' => PASSWD,
        'charset'  => 'utf8',
        'hostname' => HOST,
        ],
    'project'     => [
        'name'     => 'Clover',
        ],
    'queue'       => [
        'logfile'  => defined('LOG_QUEUE') ? LOG_DIR . '/' . LOG_QUEUE : null,
    ],
];
