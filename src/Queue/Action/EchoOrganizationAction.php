<?php

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2016 MagnaFacta BV
 * @license    No free license, do not copy
 */

namespace Gems\Clover\Queue\Action;

use Gems\HL7\Node\Message;

/**
 *
 *
 * @package    Gems
 * @subpackage Clover\Queue
 * @copyright  Copyright (c) 2016 MagnaFacta BV
 * @license    Not licensed, do not copy
 * @since      Class available since version 1.8.1 Oct 20, 2016 404661
 */
class EchoOrganizationAction implements QueueActionInterface
{
    /**
     *
     * @param int $queueId
     * @param Message $message
     * @return boolean True on success
     */
    public function execute($queueId, Message $message = null)
    {
        echo $message->getMshSegment()->getSendingApplication() . "\n";

        return true;
    }

    /**
     * Return true if this action is triggered by this message
     *
     * @param int $messageId
     * @param Message $message
     * @return boolean
     */
    public function isTriggered($messageId, Message $message)
    {
        return true;
    }
}
