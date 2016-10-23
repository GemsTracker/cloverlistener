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
use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue\Action
 * @copyright  Copyright (c) 2016, Erasmus MC and MagnaFacta B.V.
 * @license    New BSD License
 * @since      Class available since version 1.8.1 Oct 23, 2016 12:34:13 PM
 */
abstract class AbstractSaveAction implements QueueActionInterface, TargetInterface
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
     * @var \Gems\HL7\Extractor\ExtractorInterface
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
     * Called after the check that all required registry values
     * have been set correctly has run.
     *
     * @return void
     */
    public function afterRegistry()
    {
        $this->_extractor = $this->getExtractor();
    }

    /**
     * Initialize the extractor
     *
     * @return \Zalt\Loader\ProjectOverloader
     */
    abstract protected function getExtractor();

    /**
     *
     * @param int $queueId
     * @param Message $message
     * @param ActionResult $result
     * @return boolean True on success
     */
    public function execute($queueId, Message $message, ActionResult $result)
    {
        if ($this->isTriggered(null, $message)) {
            $row = $this->_extractor->extractRow($message);
            if ($row) {
                $this->startProcess($row, $result);
            } else {
                $result->setSucces(false);
                $result->setMessage("Missing key data");
            }
        }
    }

    /**
     * Return true if this action is triggered by this message
     *
     * @param int $messageId
     * @param Message $message
     * @return boolean
     */
    // abstract public function isTriggered($messageId, Message $message)

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
        // return;

        $output = [];
        $status = 0;
        exec($execute, $output, $status);

        $result->setSucces(0 === $status);
        $result->setMessage(trim(implode("\n", $output)));

        echo $result->message . "\n";
    }
}
