<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2014 Erasmus MC
 * @license    New BSD License
 */

use Zalt\Db\DbFactory;
use Zalt\Loader\ProjectOverloader;
use Zend\Console\Getopt;

defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');
defined('CONFIG_DIR')      || define('CONFIG_DIR', __DIR__ . '/config');
defined('LOG_DIR')         || define('LOG_DIR',    __DIR__ . '/var/logs');
defined('VENDOR_DIR')      || define('VENDOR_DIR', __DIR__ . '/vendor');

if (! file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0777, true);
}
ini_set('error_log', LOG_DIR . '/php_errors.log');

require VENDOR_DIR . '/autoload.php';

$config = require(CONFIG_DIR . '/config.php');
if (isset($config['queue'])) {
    $queueOptions = [$config['queue']];
} else {
    $queueOptions = [];
}

$loader = new ProjectOverloader([
    $config['project']['name'],
    'Gems\\Clover',
    'Gems',
    'PharmaIntelligence',
    ]);

$loader->createServiceManager([
        'db'            => DbFactory::creatorForServiceManager($config['database']),
        'messageLoader' => 'Message\\MessageLoader',
        'queueManager'  => ['Queue\\QueueManager', $queueOptions],
]);

try {
    $args = new Getopt([
                           'clean|c=i' => 'Clean old messages and queue: days to keep data, e.g. 14',
                           'help|h'    => 'Display this help',
                           'install|i' => 'Install the application',
                           'listen|l'  => 'Listen to HL7 queue - DEFAULT action',
                           'queue|q=s' => 'Queue commands: all, rebuild, rerun',
                       ]);
    $args->parse();

    if ($args->getOption('clean')) {
        $options = isset($config['cleanup']) ? $config['cleanup'] : null;
        $application = $loader->create('Clover\\Cleaner', $args->getOption('clean'), $options);
    } elseif ($args->getOption('queue')) {
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
