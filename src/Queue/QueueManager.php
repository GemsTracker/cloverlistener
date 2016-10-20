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

use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;
use Zend\Db\Sql\Literal;
use Zend\Db\TableGateway\TableGateway;

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
     * @var \Zend\Db\TableGateway\TableGateway The queue table
     */
    protected $_queueTable ;

    /**
     *
     * @var AdapterInterface
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

    /**
     * Load the action classes into an array
     */
    protected function _initActionClasses()
    {
        $classes = require CONFIG_DIR . '/queue.config.php';

        $this->_actionClasses = array();
        foreach ($classes as $actionClassName) {
            $actionClass = $this->loader->create('Queue\\Action\\' . $actionClassName);
            $this->_actionClasses[get_class($actionClass)] = $actionClass;
        }
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
        $this->_initActionClasses();
        $this->_initQueueTable();
    }

    public function executeQueueItem($queueId, $actionClass, Message $message)
    {
        if (isset($this->_actionClasses[$actionClass])) {
            $action = $this->_actionClasses[$actionClass];

            if ($action instanceof Action\QueueActionInterface) {
                if ($action->execute($queueId, $message)) {
                    echo "Executed QueueId $queueId\n";
                }
            }
        }
    }

    /**
     *
     * @param int $messageId
     * @param Message $message
     * @return boolean True when there is a message in the QUEUE
     */
    public function processMessage($messageId, Message $message)
    {
        $output = false;

        foreach ($this->_actionClasses as $actionClass => $action) {
            if ($action instanceof Action\QueueActionInterface) {
                if ($action->isTriggered($messageId, $message)) {
                    $queueId = $this->saveToQueue($messageId, $actionClass);

                    if ($queueId) {
                        $output = true;

                        echo "QueueId $queueId\n";
                    }
                }
            }
        }

        return $output;
    }

    /**
     *
     * @param int $messageId
     * @param stromg $actionClass
     * @return int The queue id of this message/action combo
     */
    public function saveToQueue($messageId, $actionClass)
    {
        $values = [
            'hq_message_id'   => $messageId,
            'hq_action_class' => $actionClass,
            ];

        $result = $this->_queueTable->select($values);

        if ($result->current()) {
            return $result->current()->offsetGet('hq_queue_id');
        }

        $values['hq_changed'] = new Literal('CURRENT_TIMESTAMP');

        if ($this->_queueTable->insert($values)) {
            return $this->_queueTable->getLastInsertValue();
        }

        return false;
    }
}
