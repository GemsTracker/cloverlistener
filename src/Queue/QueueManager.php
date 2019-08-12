<?php

/**
 *
 * @package    Gems
 * @subpackage Clover\Queue
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2016 cloverlistener
 * @license    No free license, do not copy
 */

namespace Gems\Clover\Queue;

use Gems\HL7\Node\Message;

use Zalt\Db\Sql\Literal\CurrentTimestampLiteral;
use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;
use Zend\Db\Sql\Literal;

/**
 *
 *
 * @package    Gems
 * @subpackage Clover\Queue
 * @copyright  Copyright (c) 2016 cloverlistener
 * @license    Not licensed, do not copy
 * @since      Class available since version 1.8.1 Oct 20, 2016 404661
 */
class QueueManager implements TargetInterface
{
    use TargetTrait;

    /**
     *
     * @var array
     */
    protected $_actionClasses;

    /**
     *
     * @var resource
     */
    protected $_logFile;

    /**
     * @var \Zalt\Db\TableGateway\TableGateway The message table
     */
    protected $_queueTable ;

    /**
     *
     * @var \Zalt\Db\DbBridge
     */
    protected $db;

    /**
     *
     * @var \Zalt\Loader\ProjectOverloader
     */
    protected $loader;

    /**
     * @var string The name of the queue table
     */
    protected $queueTableName = 'hl7_queue';

    public function __construct($options = [])
    {
        if (isset($options['logfile'])) {
            $this->_logFile = fopen($options['logfile'], 'a');
            fwrite($this->_logFile, 'Started queue log at ' . date('c') . PHP_EOL);
        }
    }

    /**
     * Load the action classes into an array
     */
    protected function _initActionClasses()
    {
        $classes = require CONFIG_DIR . '/queue.config.php';

        $subLoader = $this->loader->createSubFolderOverloader('Clover\\Queue\\Action');

        $this->_actionClasses = array();
        foreach ($classes as $actionName => $actionClassName) {
            $actionClass = $subLoader->create($actionClassName);

            if ($this->_logFile) {
                $actionClass->logFile = $this->_logFile;
            }
            $this->_actionClasses[$actionName] = $actionClass;
        }

        unset($subLoader);
    }

    /**
     * Helper function to set the queue table gateway
     */
    protected function _initQueueTable()
    {
        $this->_queueTable = $this->db->createTableGateway($this->queueTableName);
    }

    /**
     * Called after the check that all required registry values
     * have been set correctly has run.
     *
     * @return void
     */
    public function afterRegistry()
    {
        $this->_initActionClasses();
        $this->_initQueueTable();
    }

    /**
     *
     * @param int $queueId
     * @param Message $message
     * @param bool $deferred
     * @param string $firstLast
     * @return int 1 when execute was successful
     */
    public function executeQueueItem($queueId, Message $message, $deferred = false, $firstLast = null)
    {
        $where = ['hq_queue_id' => $queueId];

        $row = $this->_queueTable->fetchRow($where);

        if (isset($this->_actionClasses[$row['hq_action_name']])) {
            $action = $this->_actionClasses[$row['hq_action_name']];

            if ($action instanceof Action\QueueActionInterface) {
                $preVals['hq_execution_attempts'] = $row['hq_execution_attempts'] + 1;
                $preVals['hq_last_execution']     = new CurrentTimestampLiteral();

                $this->_queueTable->update($preVals, $where);
                $result = new Action\ActionResult();

                $action->execute($queueId, $message, $result, $deferred, $firstLast);

                $posVals['hq_execution_result'] = $result->message;
                $posVals['hq_execution_ok']     = $result->succes ? 1 : 0;
                if ($result->succes) {
                    $posVals['hq_execution_count'] = $row['hq_execution_count'] + 1;
                }

                $posVals['hq_changed']     = new CurrentTimestampLiteral();

                $this->_queueTable->update($posVals, $where);

                if ($result->succes) return 1;
            }
        }

        return 0;
    }

    /**
     *
     * @param int $messageId
     * @param Message $message
     * @return array Array of queueId => actionName
     */
    public function processMessage($messageId, Message $message)
    {
        $output = [];

        foreach ($this->_actionClasses as $actionName => $action) {
            if ($action instanceof Action\QueueActionInterface) {
                if ($action->isTriggered($messageId, $message)) {
                    $queueId = $this->saveToQueue($messageId, $actionName);

                    if ($queueId) {
                        $output[] = $queueId;
                    }
                }
            }
        }

        return $output;
    }

    /**
     *
     * @param int $messageId
     * @param string $actionName
     * @return int The queue id of this message/action combo
     */
    public function saveToQueue($messageId, $actionName)
    {
        $values = [
            'hq_message_id'  => $messageId,
            'hq_action_name' => $actionName,
            ];

        $row = $this->_queueTable->fetchRow($values);

        if ($row && isset($row['hq_queue_id'])) {
            return $row['hq_queue_id'];
        }

        $values['hq_changed'] = new CurrentTimestampLiteral();

        if ($this->_queueTable->insert($values)) {
            return $this->_queueTable->getLastInsertValue();
        }

        return false;
    }
}
