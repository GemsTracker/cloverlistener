<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2020, Erasmus MC and MagnaFacta B.V.
 * @license    New BSD License
 */

namespace Gems\Clover;

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @license    New BSD License
 * @since      Class available since version 1.8.8
 */
trait QueueTableTrait
{
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
     * @var string The name of the queue table
     */
    protected $queueTableName = 'hl7_queue';

    /**
     * Helper function to set the queue table gateway
     */
    protected function _initQueueTable()
    {
        $this->_queueTable = $this->db->createTableGateway($this->queueTableName);
    }
}