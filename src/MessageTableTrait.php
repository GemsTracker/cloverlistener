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

use Zend\Db\TableGateway\TableGateway;

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
     * @var \Zend\Db\TableGateway\TableGateway The message table
     */
    protected $_messageTable;

    /**
     * A installation specific segment loading class map
     *
     * @var array Segment name => segment class
     */
    protected $_segmentClassMap;

    /**
     *
     * @var \Zend\Db\Adapter\AdapterInterface
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
    protected $messageTableName = 'hl7_messages';

    /**
     *
     * @var \Gems\Clover\Queue\QueueManager
     */
    protected $queueManager;

    /**
     * Initialize the segment class map
     */
    protected function _initSegmentClassMap()
    {
        $this->_segmentClassMap = [
            'MSH' => $this->loader->find('HL7\\Segment\\MSHSegment'),
            'MSA' => $this->loader->find('HL7\\Segment\\MSASegment'),
            'EVN' => $this->loader->find('HL7\\Segment\\EVNSegment'),
            'PID' => $this->loader->find('HL7\\Segment\\PIDSegment'),
            'PV1' => $this->loader->find('HL7\\Segment\\PV1Segment'),
            'SCH' => $this->loader->find('HL7\\Segment\\SCHSegment'),
            ];
    }

    /**
     * Helper function to set the message table gateway
     */
    protected function _initMessageTable()
    {
        $this->_messageTable = new TableGateway($this->messageTableName, $this->db);
    }
}
