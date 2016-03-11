<?php

$config = require __DIR__ . '/config/config.php';

require 'vendor/autoload.php';

use Gems\Clover\Listener;
use React\EventLoop\Factory;
use React\Socket\Server as SocketServer;
use Zend\Db\Adapter\Adapter;

$port = $config['application']['port'];
$ip   = $config['application']['ip'];
$db   = new Adapter($config['database']);

$loop    = Factory::create();
$socket  = new SocketServer($loop);

$server = new Listener($socket);

// Set up a React stream to STDOUT to log everything to the console.
/* Logging takes 100% CPU when run using nohup, so disabled for now
if (!defined('STDIN'))
    define('STDIN', fopen('php://stdin', 'r'));
if (!defined('STDOUT'))
    define('STDOUT', fopen('php://stdout', 'w'));
if (!defined('STDERR'))
    define('STDERR', fopen('php://stderr', 'w'));
$logging = new Stream(STDOUT, $loop);
$logging->write("Starting server on $ip:$port" . PHP_EOL);
$server->initLogging($logging);
 */

$server->setDbAdapter($db);
$server->setMsgTable($config['application']['msgtable']);

$socket->listen($port, $ip);

$loop->run();