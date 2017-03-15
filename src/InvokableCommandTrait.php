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

use ZF\Console\Route;
use Zend\Console\Adapter\AdapterInterface as ConsoleAdapterInterface;

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author     Menno Dekker <menno.dekker@erasmusmc.nl>
 * @copyright  Copyright (c) 2017 Erasmus MC
 * @license    New BSD License
 */
trait InvokableCommandTrait {
    
    /**
     * This extracts the command from the route and calls the corresponding method. 
     * 
     * To extract passed parameters the route will be passed to the method. Make
     * sure to return something other than 0 in case of an error.
     * 
     * @param Route $route  The matched route
     * @param ConsoleAdapterInterface $console  The used console adapter
     * @return int 0 for ok >0 for error
     */
    public function __invoke(Route $route, ConsoleAdapterInterface $console)
    {
        $method = $route->getMatchedParam('command');
        return (int) $this->$method($route);
    }
}
