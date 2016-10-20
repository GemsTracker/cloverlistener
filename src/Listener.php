<?php
/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author Menno Dekker <menno.dekker@erasmusmc.nl>
 * @copyright  Copyright (c) 2016 Erasmus MC
 * @license    New BSD License
 */

namespace Gems\Clover;

use Gems\HL7\Node\Message;
use Gems\HL7\Segment\MSHSegment;
use Gems\HL7\Unserializer;
use PharmaIntelligence\MLLP\Server;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Server as SocketServer;
use React\Stream\Stream;
use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;
use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;

/**
 *
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2016 Erasmus MC
 * @license    New BSD License
 * @since      Class available since version 1.0.0 Oct 1, 2016 6:39:36 PM
 */
class Listener extends Server implements ApplicationInterface, TargetInterface
{
    use TargetTrait;

    /**
     *
     * @var array
     */
    protected $_config;

    /**
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $_loop;

    /**
     * A installation specific segment loading class map set in
     * _initSegmentClassMap() and used by the unserializer.
     *
     * @var array Segment name => segment class
     */
    protected $_segmentClassMap;

    /**
     *
     * @var SocketServer
     */
    protected $_socket;

    /**
     * @var Adapter
     */
    protected $db;

    /**
     *
     * @var \Zalt\Loader\ProjectOverloader
     */
    protected $loader;

    /**
     * @var Stream
     */
    protected $logging = null;

    /**
     * @var string The name of the logging table for received messages
     */
    protected $msgTable = 'hl7_messages';

    /**
     *
     * @param array $applicationConfig Application part of the config file
     */
    public function __construct(array $applicationConfig)
    {
        $this->config = $applicationConfig;

        $this->_loop   = Factory::create();
        $this->_socket = new SocketServer($this->_loop);

        parent::__construct($this->_socket);

        $this->on('data', [$this, 'onReceiving']);
    }

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

    public function initLogging(Stream $logging)
    {
        $this->logging = $logging;

        // Log connection info
        $this->on('connection', function(ConnectionInterface $connection) use($logging) {
            $logging->write('Connection from: ' . $connection->getRemoteAddress() . PHP_EOL);
        });

        // Log error info
        $this->on('error', function($errorMessage) use($logging) {
            $logging->write('Error: ' . $errorMessage . PHP_EOL);
        });

        // Log sent data
        $this->on('send', function($data) use($logging) {
            $logging->write('Sending: ' . str_replace(chr(13), PHP_EOL, $data) . PHP_EOL);
        });

        // Log received data
        $this->on('data', function($data) use($logging) {
            $logging->write('Received: ' . str_replace(chr(13), PHP_EOL, $data) . PHP_EOL);
        });
    }

    /**
     * Filter function to establish which messages to save
     *
     * @param Message $message
     * @return boolean
     */
    public function isMessageSaveable(Message $message)
    {
        return $message->getMshSegment() instanceof MSHSegment;
    }

    /**
     * The action when a message is saved
     *
     * @param type $data
     * @param ConnectionInterface $connection
     */
    public function onReceiving($data, ConnectionInterface $connection)
    {
        // $data contains a HL7 Payload
        $unserializer = $this->loader->create('HL7\\Unserializer');

        // Mainly to activate code completion. :)
        if (! $unserializer instanceof Unserializer) {
            throw new \Exception("Not a valid unserializer!");
        }

        $message = $unserializer->loadMessageFromString($data, $this->_segmentClassMap);

        if (! $message) {
            echo "Invalid message send.\n";
        }

        $saveMessage = $this->isMessageSaveable($message);
        // echo "Save msg: $saveMessage\n";

        if ($saveMessage)  {
            $messageId = $this->saveToDb($data, $message);

            echo "Msg id: $messageId\n";
        }

        $this->sendAcknowledgement($message, $connection);

        $connection->end();

        unset($unserializer);
        unset($message);
        unset($ack);

        if ($saveMessage) {
            // Queue
        }
    }

    /**
     * Start the main application
     *
     * @return void
     */
    public function run()
    {
        $this->_socket->listen($this->config['port'], $this->config['ip']);
        $this->_loop->run();
    }

    /**
     *
     * @param string $data Raw data
     * @param Message $message
     * @return int Message id from database
     */
    public function saveToDb($data, Message $message)
    {
        $msh = $message->getMshSegment();

        if ($msh) {
            $messageTable = new TableGateway($this->msgTable, $this->db);

            $values = [
                'hm_datetime'   => $msh->getDateTimeOfMessage()->getObject()->format('Y-m-d H:i:s'),
                'hm_type'       => $msh->getMessageType()->__toString(),
                'hm_msgid'      => $msh->getMessageControlId(),
                'hm_processing' => $msh->getProcessingId(),
                'hm_version'    => $msh->getVersionId(),
                'hm_message'    => $data,
                ];

            if ($messageTable->insert($values)) {
                return $messageTable->getLastInsertValue();
            }
        }

        return false;
    }

    /**
     * Send the return acknowledgement
     *
     * @param Message $message
     * @return $this
     */
    public function sendAcknowledgement(Message $message, ConnectionInterface $connection)
    {
        $ack = $this->loader->create('HL7\\Message\\ACK', $message);

        $this->send($ack, $connection);

        return $this;
    }
}