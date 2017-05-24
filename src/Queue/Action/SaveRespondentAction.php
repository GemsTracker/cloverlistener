<?php

/**
 *
 * @package    Friesland
 * @subpackage Clover\Queue
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2016 Friesland Heelkunde and MagnaFacta B.V.
 * @license    No free license, do not copy
 */

namespace Gems\Clover\Queue\Action;

use Gems\HL7\Node\Message;
use Gems\HL7\Segment\PIDSegment;

/**
 *
 * @package    Friesland
 * @subpackage Clover\Queue
 * @copyright  Copyright (c) 2016 Friesland Heelkunde and MagnaFacta B.V.
 * @license    No free license, do not copy
 * @since      Class available since version 1.8.1 Oct 21, 2016 7:21:39 PM
 */
class SaveRespondentAction extends AbstractSaveAction
{
    protected $_deferredFilename = 'respondents-hl7.csv';
    
    /**
     * Initialize the extractor
     */
    protected function getExtractor()
    {
        return $this->loader->create('HL7\\Extractor\\RespondentExtractor');
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
        return $message->getPidSegment() instanceof PIDSegment;
    }
}
