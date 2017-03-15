<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author     Menno Dekker <menno.dekker@erasmusmc.nl>
 * @copyright  Copyright (c) 2017 Erasmus MC
 * @license    New BSD License
 */

namespace Gems\Clover;

use ZF\Console\Application as ConsoleApplication;
use ZF\Console\Route;
use Zend\Console\ColorInterface as Color;

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author     Menno Dekker <menno.dekker@erasmusmc.nl>
 * @copyright  Copyright (c) 2017 Erasmus MC
 * @license    New BSD License
 */
class Application extends ConsoleApplication {
    /**
     * Display the usage message for an individual route
     * 
     * Modified to allow 
     *
     * @param Route $route
     */
    protected function showUsageMessageForRoute(Route $route, $log = false)
    {
        $console = $this->console;

        $console->writeLine('Usage:', Color::GREEN);
        $console->writeLine(' ' . $route->getRoute());
        $console->writeLine('');

        $options = $route->getOptionsDescription();
        if (! empty($options)) {
            $console->writeLine('Arguments:', Color::GREEN);

            $maxSpaces = $this->calcMaxString(array_keys($options)) + 2;

            foreach ($options as $name => $description) {
                if (!is_string($name)) {
                    $name = '';
                }
                
                $spaces = $maxSpaces - strlen($name);
                $console->write(' ' . $name, Color::GREEN);
                $console->writeLine(str_repeat(' ', $spaces) . $description);                
            }
            $console->writeLine('');
        }

        $description = $route->getDescription();
        if (! empty($description)) {
            $console->writeLine('Help:', Color::GREEN);
            $console->writeLine('');
            $console->writeLine($description);
        }
    }
}
