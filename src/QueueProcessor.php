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
use Laminas\Db\Adapter\ParameterContainer;
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
    protected function queryExecute($select, $deferred = false)
    {
        $id = 0;
        $select->limit(1000);
        $sql = new Sql($this->db);

        $check     = false; // Message comes from DB and encoding was checked before
        $executed  = 0;
        $firstLast = 'first';
        $success   = 0;

        while (true) {
            $select2 = clone $select;
            $select2->where(['hl7_queue.hq_queue_id > ?' => $id]);
            $selectString = $sql->buildSqlString($select2);
//            echo $selectString . "\n";
            $statement = $this->db->getAdapter()->query($selectString);
            $result = $statement->execute();
            if ($result instanceof ResultInterface) {
                $stop = true;
                foreach ($result as $row) {
                    $executed++;
                    $stop = false;
                    $id   = $row['hq_queue_id'];
//                    echo $id . "\n";
                    $message = $this->messageLoader->loadMessage($row['hm_message'], $check);
                    $success += $this->queueManager->executeQueueItem($row['hq_queue_id'], $message, $deferred, $firstLast);

                    $firstLast = null;
                }

                if ($stop) {
                    break;
                }
            } else {
                break;
            }
        }

        echo sprintf("%d commands executed, %d successful, %d failed.\n",
                $executed,
                $success,
                $executed - $success
        );

        if ($deferred == true) {
            return $executed;
        }
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

        $where = null;
        if ($route instanceof Route && $route->matchedParam('from')) {
            $from = $route->getMatchedParam('from');
            $fromInt = $from + 0;

            if ( is_int($fromInt) && (string) $fromInt === $from) {
                $where = ['hm_id >= ?' => $fromInt];    // Internal id, not the message id from the sending system
            } else {
                // Now check if it is a date
                $parts = explode('-', $from);
                if (count($parts) == 3) {
                    list($year, $month, $day) = $parts;
                    $date = sprintf('%04d-%02d-%02d',
                            (int) $year,
                            (int) $month,
                            (int) $day
                            );
                    if ($date === $from) {
                        $where = ['hm_datetime >= ?' => $date];   // Message date, not moment received
                    }
                }
            }

            if (is_null($where)) {
                throw new Exception('Unrecognized format for --from parameter, use integer or date yyyy-mm-dd');
            }
        }

        $sql    = $this->_messageTable->getSql();
        $select = $sql->select()
                ->columns(['hm_id', 'hm_message']);  // Save some overhead by only using the columns we need
        if (!is_null($where)) {
            $select->where($where);
        }

        $selectString = $sql->buildSqlString($select);
        $statement = $this->db->getAdapter()->createStatement($selectString);
        $result = $statement->execute();

        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);

            foreach ($resultSet as $row) {
                $this->queueManager->processMessage(
                    $row->hm_id,
                    $this->messageLoader->loadMessage($row->hm_message, $check)
                );
            }
        }
    }

    /**
     *
     * @param Route $route
     */
    public function rerun($route = null)
    {
        $sql = $this->getQueueSelect();
        if ($route instanceof Route && $route->getMatchedParam('failed', false)) {
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
                case 'all':
                    $this->rebuild();
                    $this->rerun();
                    break;

                case 'rebuild':
                    $this->rebuild();
                    break;

                case 'rerun':
                    $this->rerun();
                    break;

                default:
                    echo "Missing or unknown action command: " . $this->_action . "\n";
            }
            return;
        }

        // Still here? New style :)
        if ($route instanceof Route && $route->getMatchedParam('nonstop', false)) {
            // We want to run in continuous mode
            $this->runContinuous();
        } else {
            // We will run the queue processing all not processed messages
            echo "Run all new commands\n";

            $sql = $this->getQueueSelect()
                    ->where('hq_execution_attempts = 0');

            $this->queryExecute($sql);
        }
    }

    public function runContinuous()
    {
        $loop = \React\EventLoop\Factory::create();

        $master = $this;

        $loop->addPeriodicTimer(3, function($timer) use($master) {
            // This step might take a while so we don't run every three seconds when there is a queue
            $records = $master->runSingle();
            if ($records == 0) {
                // When there was no activity, we sleep for a while
                sleep(3);
            }
        });
        $loop->run();
    }

    public function runSingle()
    {
        $classes = require CONFIG_DIR . '/queue.config.php';
        $actionNames = array_keys($classes);

        $sql = $this->getQueueSelect()
                ->where('hq_execution_attempts = 0')
                ->where(['hq_action_name' => $actionNames])
                ->limit(300);

        return $this->queryExecute($sql, true);
    }
}