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
    'queueManager' => function() use ($loader) {
        return $loader->create('Queue\\QueueManager');
    },
    ]]);

$loader->setServiceManager($sm);

defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');

try {
    $args = new Getopt([
        'help|h'    => 'Display this help',
        'install|i' => 'Install the application',
        'listen|l'  => 'Listen to HL7 queue - DEFAULT action',
        'queue|q=s' => 'Queue commands: rebuild, empty',
        ]);
    $args->parse();

    if ($args->getOption('queue')) {
        $application = $loader->create('Clover\\QueueProcessor', $args->getOption('queue'));
    } elseif ($args->getOption('install')) {
        $application = $loader->create('Clover\\Installer');
    } elseif ($args->getOption('help')) {
        echo $args->getUsageMessage(). "\n";
        exit(0);
    } else {
        $application = $loader->create('Clover\\Listener', $config['application']);
    }
    $application->run();
    exit(0);

} catch (Zend\Console\Exception\RuntimeException $e) {
    echo $e->getUsageMessage(). "\n";
    exit(1);
}
