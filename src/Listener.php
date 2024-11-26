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

use Gems\Clover\Message\MessageLoader;
use Gems\Clover\Queue\QueueManager;
use Gems\HL7\Node\Message;
use Gems\HL7\Segment\MSHSegment;
use Laminas\Db\Adapter\AdapterInterface;
use PharmaIntelligence\MLLP\Server;
use PharmaIntelligence\MLLP\MLLPParser;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Server as SocketServer;
use Zalt\Loader\ProjectOverloader;
use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;

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
    use InvokableCommandTrait;

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

    protected $_fHandle;

    /**
     *
     * @var LoopInterface
     */
    protected $_loop;

    /**
     *
     * @var SocketServer
     */
    protected $_socket;

    /**
     * To hold partial messages
     *
     * @var string
     */
    protected $_stack = null;

    protected $_verbose = false;

    /**
     * Should we only listen or also process a message?
     *
     * @var bool
     */
    protected $runQueue = true;

    /**
     *
     * @var ProjectOverloader
     */
    protected $loader;

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
     * @param array $applicationConfig Application part of the config file
     */
    public function __construct(array $applicationConfig, $verbose = false)
    {
        $this->config   = $applicationConfig;

        $this->_loop    = Factory::create();
        $this->_socket  = new SocketServer($this->_loop);
        $this->_verbose = $verbose;

        // Add a ping function to db each xx seconds to keep the connection alive
        if ($this->_dbPingTime !== 0) {
            $this->_loop->addPeriodicTimer($this->_dbPingTime, [$this, 'checkDb']);
        }

        parent::__construct($this->_socket);

        if (isset($this->config['logfile'])) {
            $this->_fHandle = fopen($this->config['logfile'], 'a+');
        }
        $this->log(sprintf(
            "Starting server at %s on %s:%s" . PHP_EOL,
            date('c'),
            $this->config['ip'],
            $this->config['port']
        ));
        $this->initLogging();

        $this->on('data', [$this, 'onReceiving']);
        $this->on('error', [$this, 'onError']);
    }

    /**
     * @return void Check the db abd reestablish the connection if broken'
     */
    public function checkDb()
    {
        $connection = $this->db->getDriver()->getConnection();
        if ($connection instanceof \Laminas\Db\Adapter\Driver\ConnectionInterface && ! $connection->isConnected()) {
            error_log('reconnecting');
            $connection->connect();
        }
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

    public function getSetup()
    {
        $output = [];
        $output['Message segment classes'] = $this->messageLoader->getSegmentInfo();
        $output['Queue manager'] = $this->queueManager->getSetup();
        return $output;
    }

    /**
     * A message can be partial as we are reading from a buffer. Put it on a stack when it is not complete (yet)
     *
     * @param ConnectionInterface $connection
     */
    public function handleRequest(ConnectionInterface $connection) {
        $this->checkDb();

        $this->emit('connection', array($connection));
        $connection->on('data', function($data) use ($connection) {
            try {
                if (!is_null($this->_stack)) {
                    $data = $this->_stack . $data;
                    $this->_stack = null;
                }
                $data = MLLPParser::unwrap($data);
                $this->emit('data', array($data, $connection));
            } catch(\InvalidArgumentException $e) {
                // save the partial message
                $this->_stack = $data;
                // Do not stop yet
                //$this->handleInvalidMLLPEnvelope($data, $connection);
                $this->emit('error', array('Invalid MLLP envelope. Received: "'.$data.'"' . $e->getMessage()));
            }
        });
    }

    public function initLogging()
    {
        $self = $this;

        // Log connection info
        $this->on('connection', function(ConnectionInterface $connection) use ($self) {
            $self->log(sprintf(
                'Connection at %s from %s.' . PHP_EOL,
                date('c'),
                $connection->getRemoteAddress()));
        });

        // Log error info
        $this->on('error', function($errorMessage, ConnectionInterface $connection) use ($self) {
            $self->log(sprintf(
                'Error from ' . $connection->getRemoteAddress() . ' at %s: %s' . PHP_EOL,
                date('c'),
                $errorMessage));
        });

        // Log sent data
        $this->on('send', function($data, ConnectionInterface $connection) use ($self) {
            $self->log('Sending to ' . $connection->getRemoteAddress() . ' at ' . date('c') . ' data:' . PHP_EOL .
                str_replace(chr(13), PHP_EOL, $data) . PHP_EOL);
        });

        // Log received data
        $this->on('data', function($data, ConnectionInterface $connection) use ($self) {
            $self->log('Received from ' . $connection->getRemoteAddress() . ' at ' . date('c') . ' data:' . PHP_EOL .
                str_replace(chr(13), PHP_EOL, $data) . PHP_EOL);
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

    public function norun()
    {
        $this->runQueue = false;
        $this->run();
    }

    public function log($message)
    {
        if ($this->_verbose) {
            echo $message;
        }
        if (is_resource($this->_fHandle)) {
            fwrite($this->_fHandle, $message);
        }
    }

    /**
     * The action when a message is saved
     *
     * @param mixed $errorMessage
     * @param ConnectionInterface $connection
     */
    public function onError($errorMessage, ConnectionInterface $connection)
    {
        echo sprintf(
            'Error from ' . $connection->getRemoteAddress() . ' at %s: %s' . PHP_EOL,
            date('c'),
            $errorMessage);
    }

    /**
     * The action when a message is saved
     *
     * @param mixed $data
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
        // $saveMessage = false;
        // echo "Save msg: $saveMessage\n";

        if ($saveMessage)  {
            $encoding = $message->getMessageHeaderSegment()->getCharacterset();
            $internal = mb_internal_encoding();
            if ($internal != $encoding) {
                $messageId = $this->saveToDb(mb_convert_encoding($data, $internal, $encoding), $message);
            } else {
                $messageId = $this->saveToDb($data, $message);
            }

            // echo "Msg id: $messageId\n";
        }

        $this->sendAcknowledgement($message, $connection);

        // Do not end the connection as it blocks later messages
        // $connection->end();

        if ($saveMessage && $messageId) {
            $queueIds = $this->queueManager->processMessage($messageId, $message);
            // print_r($queueIds);

            if ($this->runQueue) {
                foreach ($queueIds as $queueId) {
                    $this->queueManager->executeQueueItem($queueId, $message);
                }
            }
        }

        unset($ack);
        unset($message);
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

            error_log(print_r($values, true));

            if ($this->_messageTable->insert($values)) {
                return $this->_messageTable->getLastInsertValue();
            }
        }

        return false;
    }

    /**
     * Adds connection to send event
     *
     * @param $data
     * @param ConnectionInterface $connection
     * @return void
     */
    public function send($data, ConnectionInterface $connection)
    {
        $this->emit('send', array($data, $connection));

        $connection->on('error', function(ConnectionInterface $connection, $error) {
            $this->emit('error', array('Error sending data: '.$error));
        });

        $data = MLLPParser::enclose($data);
        $connection->write($data);
        $connection->removeAllListeners('error');

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