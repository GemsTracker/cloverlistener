<?php

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue\Action
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2016, Erasmus MC and MagnaFacta B.V.
 * @license    New BSD License
 */

namespace Gems\Clover\Queue\Action;

use Gems\HL7\Node\Message;
use Gems\HL7\Segment\SCHSegment;

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue\Action
 * @copyright  Copyright (c) 2016, Erasmus MC and MagnaFacta B.V.
 * @license    New BSD License
 * @since      Class available since version 1.8.1 Oct 23, 2016 12:29:31 PM
 */
class SaveAppointmentAction extends AbstractSaveAction
{
    protected $_deferredFilename = 'appointments-hl7.csv';
    /**
     * Initialize the extractor
     */
    protected function getExtractor()
    {
        return $this->loader->create('HL7\\Extractor\\AppointmentExtractor');
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
        return $message->getSchSegment() instanceof SCHSegment;
    }
}
