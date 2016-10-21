<?php

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Expression project.copyright is undefined on line 14, column 18 in Templates/Scripting/PHPTrait.php.
 * @license    No free license, do not copy
 */

namespace Gems\Clover\Queue\Action;

use Gems\HL7\Node\Message;
use Gems\HL7\Segment\PIDSegment;

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue
 * @copyright  Expression project.copyright is undefined on line 31, column 18 in Templates/Scripting/PHPTrait.php.
 * @license    No free license, do not copy
 * @since      Class available since version 1.8.1 Oct 21, 2016 7:25:33 PM
 */
trait HasPatientDataActionTrait
{
    /**
     * Return true if this action is triggered by this message
     *
     * @param int $messageId
     * @param Message $message
     * @return boolean
     */
    public function isTriggered($messageId, Message $message)
    {
        return $message->getPidSegment() instanceof PIDSegment;
    }
}
