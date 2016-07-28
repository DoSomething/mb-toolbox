<?php
/**
 * A template for all producer classes within the Message Broker system to extend.
 */
namespace DoSomething\MB_Toolbox;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use \Exception;

/**
 * Class MB_Toolbox_BaseProducer
 *
 * @package DoSomething\MB_Toolbox
 */
abstract class MB_Toolbox_BaseProducer
{

    /**
     * Message Broker connection to RabbitMQ
     *
     * @var object
     */
    protected $messageBroker;
    
    /**
      * Singleton instance of MB_Configuration application settings and service objects
      * @var object $mbConfig
      */
    protected $mbConfig;

    /**
     * StatHat object for logging of activity
     *
     * @var object
     */
    protected $statHat;

    /**
     * The date the request message started to be generated. Note that the extending class
     * can set this value based on the final generatePayload() call.
     *
     * @var string $startTime
     */
    protected $startTime;

    /**
     * Constructor for MB_Toolbox_BaseConsumer - all consumer applications should extend this base class.
    */
    public function __construct($targetMBconfig = 'messageBroker')
    {

        $this->mbConfig = MB_Configuration::getInstance();
        $this->messageBroker = $this->mbConfig->getProperty($targetMBconfig);
        $this->statHat = $this->mbConfig->getProperty('statHat');

        $this->startTime = date('c');
    }

    /**
     * generatePayload: Basic format of message payload
     *
     * @param array
     *   Values specific to the producer to be added to the base message values.
     */
    protected function generatePayload($data)
    {

        $payload = $data;

        // Ensures consistent message structure.
        $payload['requested'] = date('c');
        $payload['startTime'] = $this->startTime;

        return $payload;
    }

    /**
     * Initial method triggered by blocked call in base mbc-??-??.php file. The $payload is the
     * contents of the message being processed from the queue.
     *
     * @param string $message
     *   The contents of a message to submit to the queue entry
     * @param string $routingKey
     *   The key to be applied to the exchange binding keys to direct the message between the bound
     *   queues.
     * @param integer $deliveryMode
     *  1: non-persistent, faster but no logging to disk, ~ 3x
     *  2: persistent, write a copy of the message to disk
     */
    protected function produceMessage($message, $routingKey = '', $deliveryMode = 1)
    {

        $payload = json_encode($message);
        $this->messageBroker->publish($payload, $routingKey, $deliveryMode);
        $this->statHat->ezCount('MB_Toolbox: MB_Toolbox_BaseProducer: produceMessage', 1);
    }
}
