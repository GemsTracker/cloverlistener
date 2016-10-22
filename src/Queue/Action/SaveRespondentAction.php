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

use Gems\HL7\Extractor\RespondentExtractor;
use Gems\HL7\Node\Message;
use Gems\HL7\Segment\PIDSegment;
use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;

/**
 *
 * @package    Friesland
 * @subpackage Clover\Queue
 * @copyright  Copyright (c) 2016 Friesland Heelkunde and MagnaFacta B.V.
 * @license    No free license, do not copy
 * @since      Class available since version 1.8.1 Oct 21, 2016 7:21:39 PM
 */
class SaveRespondentAction implements QueueActionInterface, TargetInterface
{
    use TargetTrait;

    /**
     * The code to call the GT installation
     *
     * @var string
     */
    protected $_executionCommand;

    /**
     *
     * @var \Gems\HL7\Extractor\RespondentExtractor
     */
    protected $_extractor;

    /**
     *
     * @var \Zalt\Loader\ProjectOverloader
     */
    protected $loader;

    /**
     *
     * @param string $executionCommand The code to call the GT installation
     */
    public function __construct($executionCommand)
    {
        $this->_executionCommand = $executionCommand;
    }

    /**
     * Initialize the extractor
     */
    protected function _initExtractor()
    {
        $this->_extractor = $this->loader->create('HL7\\Extractor\\RespondentExtractor');
    }

    /**
     * Called after the check that all required registry values
     * have been set correctly has run.
     *
     * @return void
     */
    public function afterRegistry()
    {
        $this->_initExtractor();
    }

    /**
     *
     * @param int $queueId
     * @param Message $message
     * @param ActionResult $result
     * @return boolean True on success
     */
    public function execute($queueId, Message $message, ActionResult $result)
    {
        $pid = $message->getPidSegment();

        if ($pid instanceof PIDSegment) {
            $this->startProcess($this->_extractor->extractPatientRow($message), $result);
        }
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

    /**
     *
     * @param array $data
     * @param ActionResult $result
     */
    public function startProcess(array $data, ActionResult $result)
    {
        $execute = $this->_executionCommand;

        // print_r($data);
        foreach ($data as $field => $value) {
            $execute .= " $field=" . escapeshellarg($value);
        }
        // echo $execute . "\n\n";

        $output = [];
        $status = 0;
        exec($execute, $output, $status);

        $result->setSucces(0 === $status);
        $result->setMessage(trim(implode("\n", $output)));

        echo $result->message . "\n";
    }
}
