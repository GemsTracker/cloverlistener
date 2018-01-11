<?php
define('VERSION', '0.3.0');

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2017 Erasmus MC
 * @license    New BSD License
 */

use Zalt\Db\DbFactory;
use Zalt\Loader\ProjectOverloader;
use Zend\Console\Console;
use Gems\Clover\Application;
use ZF\Console\Dispatcher;

defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');
defined('CONFIG_DIR') || define('CONFIG_DIR', __DIR__ . '/config');
defined('VENDOR_DIR') || define('VENDOR_DIR', __DIR__. '/vendor');

require VENDOR_DIR . '/autoload.php';

$config = require(CONFIG_DIR . '/config.php');

$loader = new ProjectOverloader([
    $config['project']['name'],
    'Gems',
    'PharmaIntelligence',
    ]);

// Add to this in your own routes.php
$serviceManager = $loader->createServiceManager([
        'db'             => DbFactory::creatorForServiceManager($config['database']),
        'messageLoader'  => 'Clover\\Message\\MessageLoader',
        'queueManager'   => 'Clover\\Queue\\QueueManager',
        'queueProcessor' => 'Clover\\QueueProcessor',
        'installer'      => 'Clover\\Installer',
        'listener'       => function () use ($config, $loader) { return $loader->create('Clover\\Listener', $config['application']); },
]);

$dispatcher = new Dispatcher($serviceManager);

// Load the routes, try custom routes first then fall-back to the routes in this project.
$routes = array();
if (file_exists(CONFIG_DIR . '/routes.php')) {
    $routes = include CONFIG_DIR . '/routes.php';
} elseif (file_exists(__DIR__ . '/config/routes.php')) {
    $routes = include __DIR__ . '/config/routes.php';
}

$application = new Application(
        'CloverListener', 
        VERSION, 
        $routes, 
        Console::getInstance(), 
        $dispatcher
);

$exit = $application->run();
exit($exit);
