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

/**
 *
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2016 cloverlistener
 * @license    Not licensed, do not copy
 * @since      Class available since version 1.8.1 Oct 20, 2016 Matijs de Jong <mjong@magnafacta.nl>
 */
trait MessageTableTrait
{
    /**
     * @var \Zalt\Db\TableGateway\TableGateway The message table
     */
    protected $_messageTable;

    /**
     *
     * @var \Zalt\Db\DbBridge
     */
    protected $db;

    /**
     * @var string The name of the queue table
     */
    protected $messageTableName = 'hl7_messages';

    /**
     * Helper function to set the message table gateway
     */
    protected function _initMessageTable()
    {
        $this->_messageTable = $this->db->createTableGateway($this->messageTableName);
    }
}
