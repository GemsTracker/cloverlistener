<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2019, Erasmus MC and MagnaFacta B.V.
 * @license    New BSD License
 */

namespace Gems\Clover;

use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2019, Erasmus MC and MagnaFacta B.V.
 * @license    New BSD License
 * @since      Class available since version 1.8.6 13-Sep-2019 14:03:39
 */
class Cleaner implements ApplicationInterface, TargetInterface
{
    use MessageTableTrait;
    use QueueTableTrait;
    use TargetTrait;
    // use InvokableCommandTrait;

    /**
     *
     * @param int $days
     */
    protected $_days;

    /**
     *
     * @param int $days
     */
    protected $_daysDefault = 14;

    /**
     * @var Stream
     */
    protected $logging = null;

    /**
     * Cleaner constructor.
     *
     * @param null $days
     */
    public function __construct($days = null, array $config)
    {
        if (intval($days) > 0) {
            $this->_days = intval($days);
        } else {
            $this->_days = $this->_daysDefault;
            if (strlen($days)) {
                echo sprintf(
                        "Invalid cleanup parameter %s, default value %d used instead.\n",
                        $days,
                        $this->_daysDefault
                        );
            }
        }
        
        if (isset($config['logfile'])) {
            $this->logging = fopen($config['logfile'], 'a');
            fwrite($this->logging, sprintf(
                "Starting cleanup run at %s for &d days." . PHP_EOL,
                date('c'),
                $this->_days
                ));
        }
    }

    /**
     * Called after the check that all required registry values
     * have been set correctly has run.
     *
     * @return void
     */
    public function afterRegistry()
    {
        $this->_initMessageTable();
        $this->_initQueueTable();
    }

    /**
     * Should be called after answering the request to allow the Target
     * to check if all required registry values have been set correctly.
     *
     * @return boolean False if required values are missing.
     */
    public function checkRegistryRequestsAnswers()
    {
        return $this->db instanceof DbBridge;
    }

    /**
     * Start the main application
     *
     * @return void
     */
    public function run()
    {
        $startDate = new \DateTime('today');
        $interval  = new \DateInterval(sprintf("P%dD", $this->_days));
        $startDate->sub($interval);

        $queueWhere = sprintf(
            "hq_created < '%s' AND hq_execution_count > 0", 
            $startDate->format('Y-m-d H:i:s'));
        $messsageWhere = sprintf(
            "hm_created < '%s' AND hm_id NOT IN (SELECT hq_message_id FROM hl7_queue)", 
            $startDate->format('Y-m-d H:i:s'));

        try {
            $queueDel = $this->_queueTable->delete($queueWhere);
            $messageDel = $this->_messageTable->delete($messsageWhere);

            $message = sprintf(
                "Cleanup log %d queue items and %d message items created before %s deleted." . PHP_EOL,
                $queueDel,
                $messageDel,
                $startDate->format('Y-m-d H:i:s')
            );
            if ($this->logging) {
                fwrite($this->logging, $message);
            }
            
            // echo $message;
        } catch (\Exception $e) {
            $message = sprintf(
                "Cleanup error at %s: %s" . PHP_EOL . $e->getTraceAsString() . PHP_EOL,
                date('c'),
                $e->getMessage());
            
            if ($this->logging) {
                fwrite($this->logging, $message);
            }
            echo $message;
        }
    }
}
