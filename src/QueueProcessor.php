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
     * @var \Gems\Clover\Message\MessageLoader
     */
    protected $messageLoader;

    /**
     *
     * @var \Gems\Clover\Queue\QueueManager
     */
    protected $queueManager;

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

        $messages = $this->_messageTable->select()->toArray();
        foreach ($messages as $messageRow) {
            // echo $messageRow['hm_id'] . "\n";
            $this->queueManager->processMessage(
                    $messageRow['hm_id'],
                    $this->messageLoader->loadMessage($messageRow['hm_message'])
                    );
        }
    }

    public function rerun()
    {
        echo "Rerun all commands\n";

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
        foreach ($queue as $queueRow) {
            // echo $queueRow['hq_queue_id'] . "\n";
            $message = $this->messageLoader->loadMessage($queueRow['hm_message']);
            $this->queueManager->executeQueueItem($queueRow['hq_queue_id'], $message);
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
