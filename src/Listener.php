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

use Gems\HL7\Message\ACK;
use Gems\HL7\Segment\MSHSegment;
use PharmaIntelligence\HL7\Unserializer;
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
     *
     * @var SocketServer
     */
    protected $_socket;

    /**
     * @var Adapter
     */
    protected $db;

    /**
     * @var Stream
     */
    protected $logging = null;

    /**
     * @var string The name of the logging table for received messages
     */
    protected $msgTable = 'hl7_messages';

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->_loop   = Factory::create();
        $this->_socket = new SocketServer($this->_loop);

        parent::__construct($this->_socket);

        $server = $this;
        $this->on('data', function ($data, ConnectionInterface $connection) use($server) {
            // $data contains a HL7 Payload
            // Parse HL7 and create an ACK message
            $unserializer = new Unserializer();

            $map     = array(
                'MSH' => 'Gems\HL7\Segment\MSHSegment',
                'MSA' => 'Gems\HL7\Segment\MSASegment',
                'EVN' => 'Gems\HL7\Segment\EVNSegment',
                'PID' => 'Gems\HL7\Segment\PIDSegment',
                'PV1' => 'Gems\HL7\Segment\PV1Segment',
                'SCH' => 'Gems\HL7\Segment\SCHSegment'
            );
            var_dump($map);
            $message = $unserializer->loadMessageFromString($data, $map);
            if (count($message->getSegmentsByName('MSA')) > 0) {
                // Response
                // @todo: Handle the response probably not needed as this will be initiated by a client
                $mshs = $message->getSegmentsByName('MSH');
                $server->msgToDb($data, $mshs[0]);

                $ack = new ACK($message);

                $server->send($ack, $connection);

            } else {
                // Notification
                $mshs = $message->getSegmentsByName('MSH');
                $server->msgToDb($data, $mshs[0]);

                $ack = new ACK($message);

                $server->send($ack, $connection);
            }

            $connection->end();

            unset($unserializer);
            unset($map);
            unset($message);
            unset($ack);
        });
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

    public function msgToDb($data, MSHSegment $msh)
    {
        echo "MSG " . $this->msgTable . ' - ' . get_class($this->db) . "\n";
        if (!($this->db instanceof Adapter) || empty($this->msgTable)) {
            return;
        }

        $messageTable = new TableGateway($this->msgTable, $this->db);

        $values = array(
            'hm_datetime'   => $msh->getDateTimeOfMessage()->getObject()->format('Y-m-d H:i:s'),
            'hm_type'       => $msh->getMessageType()->__toString(),
            'hm_msgid'      => $msh->getMessageControlId(),
            'hm_processing' => $msh->getProcessingId(),
            'hm_version'    => $msh->getVersionId(),
            'hm_message'    => $data
        );
        echo "MSG Saved\n";
        $result = $messageTable->insert($values);
    }

    public function run()
    {
        echo "RUN\n";
        $this->_socket->listen($this->config['application']['port'], $this->config['application']['ip']);
        $this->_loop->run();
    }

    public function setMsgTable($tableName)
    {
        $this->msgTable = $tableName;
    }

}