<?php

require __DIR__ . '/db.inc.php';

return array(
    /* Application configuration */
    'application' => array(
        'ip'       => gethostbyname(gethostname()), // The ip address of this machine
        'port'     => 23887,                        // The port it will listen on
        'msgtable' => 'hl7messages'                 // The table to store messages for reference
    ),
    /*  Database connector */
    'database'    => array(
        'driver'   => 'Mysqli',
        'database' => DATABASE,
        'username' => USER,
        'password' => PASSWD,
        'charset'  => 'utf8',
        'hostname' => HOST
    ),
    'project'     => array(
        'name'     => 'Clover',
    ),
);
