<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2014 Erasmus MC
 * @license    New BSD License
 */

use Gems\Clover\Listener;
use React\EventLoop\Factory;
use React\Socket\Server as SocketServer;
use Zend\Db\Adapter\Adapter;

defined('CONFIG_DIR') || define('CONFIG_DIR', __DIR__ . '/config');
defined('VENDOR_DIR') || define('VENDOR_DIR', dirname(__DIR__). '/vendor');

require VENDOR_DIR . '/autoload.php';

$config = require(CONFIG_DIR . '/config.php');

defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');

//**

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
// */