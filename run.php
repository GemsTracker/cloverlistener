<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2014 Erasmus MC
 * @license    New BSD License
 */

use Gems\Clover\Installer;
use React\EventLoop\Factory;
use React\Socket\Server as SocketServer;
use Zalt\Loader\ProjectOverloader;
use Zend\Console\Getopt;
use Zend\Db\Adapter\Adapter;
use Zend\ServiceManager\ServiceManager;

defined('CONFIG_DIR') || define('CONFIG_DIR', __DIR__ . '/config');
defined('VENDOR_DIR') || define('VENDOR_DIR', dirname(__DIR__). '/vendor');

require VENDOR_DIR . '/autoload.php';

$config = require(CONFIG_DIR . '/config.php');
$loader = new ProjectOverloader([
    $config['project']['name'],
    'Gems\\Clover',
    'Gems',
    'PharmaIntelligence',
    ]);

$sm = new ServiceManager(['factories' => [
    'db' => function() use ($config) {
            // echo "DB\n";
            $db = new Adapter($config['database']);

            $db->getDriver()->getConnection()->connect();

            return $db;
        },
    ]]);

$loader->setServiceManager($sm);

defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');

try {
    $args = new Getopt([
        'help|h'    => 'Display this help',
        'install|i' => 'Install the application',
        'listen|l'  => 'Listen to HL7 queue - DEFAULT action',
        // 'queue|q'   => 'Check queue and execute commands',
        ]);
    $args->parse();

    if ($args->getOption('queue')) {
        // TODO
        require __DIR__ . '/queue.php';
    } elseif ($args->getOption('install')) {
        $application = $loader->create('Clover\Installer');
        $application->run();
        exit(0);
    } elseif ($args->getOption('help')) {
        echo $args->getUsageMessage(). "\n";
        exit(0);
    } else {
        $application = $loader->create('Clover\Listener', $config['application']);
        $application->run();
        exit(0);
    }

} catch (Zend\Console\Exception\RuntimeException $e) {
    echo $e->getUsageMessage(). "\n";
    exit(1);
}
echo "OK\n";

/**

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
 * /

$server->setDbAdapter($db);
$server->setMsgTable($config['application']['msgtable']);

$socket->listen($port, $ip);

$loop->run();
// */