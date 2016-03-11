<?php
namespace Gems\Clover;

use Evenement\EventEmitterInterface;
use Gems\HL7\Message\ACK;
use Gems\HL7\Segment\MSHSegment;
use PharmaIntelligence\HL7\Unserializer;
use PharmaIntelligence\MLLP\Server;
use React\Socket\ConnectionInterface;
use React\Stream\Stream;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterAwareInterface;
use Zend\Db\TableGateway\TableGateway;

/**
 * Description of Listener
 *
 * @author Menno Dekker <menno.dekker@erasmusmc.nl>
 */
class Listener extends Server implements AdapterAwareInterface {

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
    protected $msgTable = null;

    public function __construct(EventEmitterInterface $io) {
        parent::__construct($io);

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

    public function initLogging(Stream $logging) {
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

    public function msgToDb($data, MSHSegment $msh) {
        if (!($this->db instanceof Adapter) || empty($this->msgTable)) {
            return;
        }

        $messageTable = new TableGateway($this->msgTable, $this->db);

        $values = array(
            'datetime'   => $msh->getDateTimeOfMessage()->getObject()->format('Y-m-d H:i:s'),
            'type'       => $msh->getMessageType()->__toString(),
            'msgid'      => $msh->getMessageControlId(),
            'processing' => $msh->getProcessingId(),
            'version'    => $msh->getVersionId(),
            'message'    => $data
        );

        $result = $messageTable->insert($values);
    }

    public function setDbAdapter(Adapter $adapter) {
        $this->db = $adapter;
    }
    
    public function setMsgTable($tableName) {
        $this->msgTable = $tableName;
    }

}