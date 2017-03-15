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

use Exception;
use Gems\Clover\Message\MessageLoader;
use Gems\Clover\Queue\QueueManager;
use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;
use ZF\Console\Route;

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
    use InvokableCommandTrait;

    /**
     * @var string
     */
    protected $_action;

    /**
     *
     * @var MessageLoader
     */
    protected $messageLoader;

    /**
     *
     * @var QueueManager
     */
    protected $queueManager;

    /**
     *
     * @param string $action
     */
    public function __construct($action = null)
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
     * @return Select
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
     * Execute the sql, load the message from the database and execute it.
     * 
     * @param Select $select
     * @return void
     */
    protected function queryExecute($select)
    {
        $sql = new Sql($this->db);
        $selectString = $sql->buildSqlString($select);
        $result = $this->db->getAdapter()->query($selectString, Adapter::QUERY_MODE_EXECUTE);

        if (! ($result instanceof ResultSet)) {
            return;
        }        
        
        $executed = 0;
        $success  = 0;

        $check = false; // Message comes from DB and encoding was checked before
        foreach ($result as $queueRow) {
            $executed++;
            // echo $queueRow['hq_queue_id'] . "\n";
            $message = $this->messageLoader->loadMessage($queueRow['hm_message'], $check);
            $result  = $this->queueManager->executeQueueItem($queueRow['hq_queue_id'], $message);
            
            $success = $success + $result;
        }
        
        echo sprintf("%d commands executed, %d successful, %d failed.\n", 
                $executed, 
                $success, 
                $executed - $success
        );
    }

    /**
     * Rebuild the queue using the message table
     *
     * @throws Exception
     */
    public function rebuild($route = null)
    {
        $this->_initMessageTable();

        echo "Rebuilding\n";

        $check = false; // Message comes from DB and encoding was checked before
        $messages = $this->_messageTable->select()->toArray();
        foreach ($messages as $messageRow) {
            // echo $messageRow['hm_id'] . "\n";
            $this->queueManager->processMessage(
                    $messageRow['hm_id'],
                    $this->messageLoader->loadMessage($messageRow['hm_message'], $check)
                    );
        }
    }

    /**
     *
     * @param Route $route
     */
    public function rerun($route = null)
    {
        $sql = $this->getQueueSelect();
        if ($route instanceof Route && $route->matchedParam('failed')) {
            echo "Rerun failed commands\n";

            $sql->where('hq_execution_attempts > 0')
                ->where('hq_execution_ok = 0');            
        } else {
            echo "Rerun all commands\n";            
        }

        $this->queryExecute($sql);
    }

    /**
     * Start the main application
     *
     * @return void
     */
    public function run($route = null)
    {
        // Fallback for old style
        if (is_null($route)) {
            switch (strtolower($this->_action)) {
                case 'rebuild':
                    $this->rebuild();

                case 'rerun':
                    $this->rerun();

                default:
                    echo "Missing or unknown action command: " . $this->_action . "\n";
            }
            return;
        }

        // Still here? New style :)
        // We will run the queue processing all not processed messages
        echo "Run all new commands\n";

        $sql = $this->getQueueSelect()
                ->where('hq_execution_attempts = 0');

        $this->queryExecute($sql);
    }
}