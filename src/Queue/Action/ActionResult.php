<?php

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue\Action
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2016, Erasmus MC and MagnaFacta B.V.
 * @license    No free license, do not copy
 */

namespace Gems\Clover\Queue\Action;

/**
 * Output communication class
 * 
 * @package    Gems
 * @subpackage Clover\Queue\Action
 * @copyright  Copyright (c) 2016, Erasmus MC and MagnaFacta B.V.
 * @license    No free license, do not copy
 * @since      Class available since version 1.8.1 Oct 22, 2016 8:09:38 PM
 */
class ActionResult
{
    public $message;
    public $succes = false;

    public function setMessage($message)
    {
        $this->message = $message;
    }
    public function setSucces($succes = true)
    {
        $this->succes = $succes;
    }
}
