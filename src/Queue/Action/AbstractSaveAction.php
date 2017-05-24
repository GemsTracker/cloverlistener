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
     * The call for the deferred command
     *
     * @var string
     */
    protected $_deferredCommand;

    /**
     * The filename to use for storing deferred files
     *
     * @var string
     */
    protected $_deferredFilename;

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
    public function __construct($executionCommand, $deferredCommand = null, $deferredFilename = null)
    {
        $this->_executionCommand = $executionCommand;
        $this->_deferredCommand = $deferredCommand;
        $this->_deferredFilename = $deferredFilename;
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

    public function deferredProcess(array $data, ActionResult $result, $firstLast)
    {
        $file = $this->getFileHandle();

        if ($firstLast === 'first') {
            fputcsv($file, array_keys($data));
        }

        fputcsv($file, $data);
        fclose($file);

        if ($firstLast === 'last') {
            $this->startDeferredProcess();
        }
    }

    /**
     *
     * @param int $queueId
     * @param Message $message
     * @param ActionResult $result
     * @param boolean $deferred
     * @param string $firstLast
     * @return boolean True on success
     */
    public function execute($queueId, Message $message, ActionResult $result, $deferred = true, $firstLast = null)
    {
        if ($this->isTriggered(null, $message)) {
            $row = $this->_extractor->extractRow($message);
            if ($row) {
                if ($deferred) {
                    $this->deferredProcess($row, $result, $firstLast);
                } else {
                    $this->startProcess($row, $result);
                }
            } else {
                $result->setSucces(false);
                $result->setMessage("Missing key data");
            }
        }
    }

    protected function getFileHandle()
    {
        return fopen($this->_deferredFilename, 'a');
    }

    /**
     * Return true if this action is triggered by this message
     *
     * @param int $messageId
     * @param Message $message
     * @return boolean
     */
    // abstract public function isTriggered($messageId, Message $message)
    
    public function startDeferredProcess(array $data, ActionResult $result)
    {
        $execute = $this->_deferredCommand;

        $output = [];
        $status = 0;
        exec($execute, $output, $status);

        $result->setSucces(0 === $status);
        $result->setMessage(trim(implode("\n", $output)));

        echo $result->message . "\n";
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
        // return;

        $output = [];
        $status = 0;
        exec($execute, $output, $status);

        $result->setSucces(0 === $status);
        $result->setMessage(trim(implode("\n", $output)));

        echo $result->message . "\n";
    }
}
