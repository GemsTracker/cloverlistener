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
use Zend\Db\Adapter\AdapterInterface;
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
    use MessageTableTrait;
    use TargetTrait;

    /**
     *
     * @var array
     */
    protected $_config;
    
    /**
     * The time in seconds to ping the database
     * 
     * During periods of inactivity the mysql server can close the connection
     * due to inactivity. To prevent this we can ping the connection using this
     * interval. If connection fails the application will exit with an errorlevel
     * so respawning is possible.
     * 
     * Set to 0 to disable sending pings to the database. Mysql default timeout 
     * is 28800 seconds which is 8 hours so choose a reasonable time here.
     * 
     * @var int
     */
    protected $_dbPingTime = 600;   // 10 minutes
    
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
     *
     * @var \Zalt\Loader\ProjectOverloader
     */
    protected $loader;

    /**
     *
     * @var \Gems\Clover\Message\MessageLoader
     */
    protected $messageLoader;

    /**
     * @var Stream
     */
    protected $logging = null;

    /**
     *
     * @var \Gems\Clover\Queue\QueueManager
     */
    protected $queueManager;

    /**
     *
     * @param array $applicationConfig Application part of the config file
     */
    public function __construct(array $applicationConfig)
    {
        $this->config = $applicationConfig;

        $this->_loop   = Factory::create();
        $this->_socket = new SocketServer($this->_loop);
        
        // Add a ping function to db each xx seconds to keep the connection alive
        if (!$this->_dbPingTime === 0) {
            $this->_loop->addPeriodicTimer($this->_dbPingTime, [$this, 'pingDb']);
        }

        parent::__construct($this->_socket);
        
        /*
        if (!defined('STDIN'))
            define('STDIN', fopen('php://stdin', 'r'));
        if (!defined('STDOUT'))
            define('STDOUT', fopen('php://stdout', 'w'));
        if (!defined('STDERR'))
            define('STDERR', fopen('php://stderr', 'w'));
        $logging = new Stream(STDOUT, $this->_loop);
        $ip   = $this->config['ip'];
        $port = $this->config['port'];
        $logging->write("Starting server on $ip:$port" . PHP_EOL);
        $this->initLogging($logging);
         */

        $this->on('data', [$this, 'onReceiving']);
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
        // Do something here to make sure the encoding is correct
        // echo mb_check_encoding($data, 'WINDOWS-1252');
        // echo mb_check_encoding($data, 'UTF-8');
        // echo mb_convert_encoding($data, 'UTF-8', 'WINDOWS-1252');
        $message = $this->messageLoader->loadMessage($data);

        if (! $message) {
            echo "Invalid message send.\n";
        }

        $saveMessage = $this->isMessageSaveable($message);
        // echo "Save msg: $saveMessage\n";

        if ($saveMessage)  {
            $messageId = $this->saveToDb($data, $message);

            // echo "Msg id: $messageId\n";
        }

        $this->sendAcknowledgement($message, $connection);

        // Do not end the connection as it blocks later messages
        // $connection->end();

        if ($saveMessage && $messageId) {
            $queueIds = $this->queueManager->processMessage($messageId, $message);

            foreach ($queueIds as $queueId) {
                $this->queueManager->executeQueueItem($queueId, $message);
            }
        }

        unset($ack);
        unset($message);
    }
    
    /**
     * Ping db to keep it alive and fail on error
     * 
     * When there is no activity mysql will close the connection. When this is picked up on an message, it will not be picked up.
     */
    public function pingDb()
    {
        if (@$this->db->getDriver()->getConnection()->getResource()->ping() === false) {
            exit(255);
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
            if (! $this->_messageTable) {
                $this->_initMessageTable();
            }
            
            $values = [
                'hm_datetime'   => $msh->getDateTimeOfMessage()->getObject()->format('Y-m-d H:i:s'),
                'hm_type'       => $msh->getMessageType()->__toString(),
                'hm_msgid'      => $msh->getMessageControlId(),
                'hm_processing' => $msh->getProcessingId(),
                'hm_version'    => $msh->getVersionId(),
                'hm_message'    => $data,
                ];

            if ($this->_messageTable->insert($values)) {
                return $this->_messageTable->getLastInsertValue();
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