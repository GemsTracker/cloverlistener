<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2016 cloverlistener
 * @license    No free license, do not copy
 */

namespace Gems\Clover;

use Gems\HL7\Node\Message;
use Gems\HL7\Unserializer;
use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Sql;

/**
 *
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2016 cloverlistener
 * @license    Not licensed, do not copy
 * @since      Class available since version 1.8.1 Oct 20, 2016 Matijs de Jong <mjong@magnafacta.nl>
 */
class QueueProcessor implements ApplicationInterface, TargetInterface
{
    use MessageTableTrait;
    use TargetTrait;

    /**
     * @var string
     */
    protected $_action;

    /**
     *
     * @param string $action
     */
    public function __construct($action)
    {
        $this->_action = $action;
    }

    /**
     * Helper function to set the queue table gateway
     */
    protected function _initQueueTable()
    {
        $this->_queueTable = new TableGateway($this->queueTableName, $this->db);
    }

    /**
     * Called after the check that all required registry values
     * have been set correctly has run.
     *
     * @return void
     */
    public function afterRegistry()
    {
        $this->_initSegmentClassMap();
    }

    /**
     * Should be called after answering the request to allow the Target
     * to check if all required registry values have been set correctly.
     *
     * @return boolean False if required values are missing.
     */
    public function checkRegistryRequestsAnswers()
    {
        return $this->db instanceof AdapterInterface;
    }

    /**
     *
     * @return \Zend\Db\Sql\Select
     */
    protected function getQueueSelect()
    {
        $sql = new Sql($this->db);
        $select = $sql->select('hl7_queue');
        $select->join('hl7_messages', 'hl7_queue.hq_message_id = hl7_messages.hm_id')
                ->order('hl7_queue.hq_queue_id');

        return $select;
    }

    /**
     * Rebuild the queue using the message table
     *
     * @throws \Exception
     */
    public function rebuild()
    {
        $this->_initMessageTable();

        echo "Rebuilding\n";

       // $data contains a HL7 Payload
        $unserializer = $this->loader->create('HL7\\Unserializer');

        // Mainly to activate code completion. :)
        if (! $unserializer instanceof Unserializer) {
            throw new \Exception("Not a valid unserializer!");
        }

        $messages = $this->_messageTable->select()->toArray();

        foreach ($messages as $messageRow) {

            $messageId = $messageRow['hm_id'];
            $message   = $unserializer->loadMessageFromString($messageRow['hm_message'], $this->_segmentClassMap);
            // echo "$messageId\n";
            $this->processForQueue($messageId, $message);
        }
    }

    public function rerun()
    {
        $sql = "SELECT *
            FROM hl7_queue INNER JOIN hl7_messages ON hl7_queue.hq_message_id = hl7_messages.hm_id
            ORDER BY hl7_queue.hq_queue_id ASC";

        $stmt = $this->db->getDriver()->createStatement($sql);
        $stmt->prepare();
        $result = $stmt->execute();

        if (! ($result instanceof ResultInterface && $result->isQueryResult())) {
            return;
        }
        $resultSet = new ResultSet;
        $resultSet->initialize($result);

        $queue = $resultSet->toArray();
        $unserializer = $this->loader->create('HL7\\Unserializer');

        // Mainly to activate code completion. :)
        if (! $unserializer instanceof Unserializer) {
            throw new \Exception("Not a valid unserializer!");
        }

        foreach ($queue as $queueRow) {
            $message = $unserializer->loadMessageFromString($queueRow['hm_message'], $this->_segmentClassMap);
            $this->queueManager->executeQueueItem($queueRow['hq_queue_id'],$queueRow['hq_action_class'], $message);
        }
    }

    /**
     * Start the main application
     *
     * @return void
     */
    public function run()
    {
        switch (strtolower($this->_action)) {
            case 'rebuild':
                $this->rebuild();
                return;

            case 'rerun':
                $this->rerun();
                return;

            default:
                echo "Missing or unknown action command: " . $this->_action . "\n";
        }
    }
}
