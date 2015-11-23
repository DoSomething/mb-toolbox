<?php
/**
 * Base class for classes within the Message Broker system to extend. Focused on consuming
 * messages from queues a RabbitMQ server.
 */

namespace DoSomething\MB_Toolbox;

use DoSomething\MB_Toolbox\MB_Configuration;
use \Exception;

/*
 * MB_Toolbox_BaseConsumer(): Used to process the transactionalQueue
 * entries that match the campaign.*.* binding.
 */
abstract class MB_Toolbox_BaseConsumer
{

   // The number of seconds to pause when throttling is triggered
   const THROTTLE_TIMEOUT = 5;

  /**
   * Singleton instance of MB_Configuration application settings and service objects
   * @var object $mbConfig
   */
  protected $mbConfig;

  /**
   * Message Broker connection to RabbitMQ
   * @var object $messageBroker
   */
  protected $messageBroker;

  /**
   * The channel use by the Message Broker connection to RabbitMQ
   * @var object $channel
   */
  protected $channel;

  /**
   * StatHat object for logging of activity
   * @var object $statHat
   */
  protected $statHat;

  /**
   * Value of message from queue to be consumed / processed.
   * @var array $message
   */
  protected $message;

  /**
   * The number of messages that have been processed. Used to calculate the message rate
   * to trigger throttling.
   * @var integer $throttleMessageCount
   */
  protected $throttleMessageCount = 0;

   /**
   * A second value to track the lapsed time to calculate the massage rate.
   * @var integer $throttleSecondStamp
   */
  protected $throttleSecondStamp = NULL;

  /**
   * The date the request message started to be generated.
   * @var string $startTime
   */
  protected $startTime;

  /**
   * A connection object to the RabbitMQ Management API.
   * @var object $mbRabbitMQManagementAPI
   */
  protected $mbRabbitMQManagementAPI;


  /**
   * Constructor for MBC_BaseConsumer - all consumer applications should extend this base class.
   *
   * @param string $targetMBconfig
   *   The Message Broker object used to interface the RabbitMQ server exchanges and related queues.
   */
  public function __construct($targetMBconfig = 'messageBroker') {

    $this->mbConfig = MB_Configuration::getInstance();

    $this->settings = $this->mbConfig->getProperty('generalSettings');
    $this->messageBroker = $this->mbConfig->getProperty($targetMBconfig);
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbRabbitMQManagementAPI = $this->mbConfig->getProperty('mbRabbitMQManagementAPI');

    $connection = $this->messageBroker->connection;
    $this->channel = $connection->channel();
  }

  /**
   * Initial method triggered by blocked call in base mbc-??-??.php file. The $payload is the
   * contents of the message being processed from the queue.
   *
   * @param array $payload
   *   The contents of the queue entry
   */
  public function consumeQueue($payload) {

    try {
      $this->message = unserialize($payload->body);
      $this->message['original'] = $this->message;
      $this->message['payload'] = $payload;
    }
    catch(Exception $e) {
      echo 'MB_Toolbox_BaseConsumer: Error unseralizing payload: consumeQueue(): ' . $e->getMessage();
    }

  }

  /**
   * Log payload with RabbitMQ objects removed for clarity.
   */
  private function reportErrorPayload() {

    $errorPayload = $this->message;
    unset($errorPayload['payload']);
    unset($errorPayload['original']);
    echo '-> message: ' . print_r($errorPayload, TRUE), PHP_EOL;
  }

   /**
   * Throddle the rate the consumer processes messages.
   *
   * @param integer $maxMessageRate
   *   The number of messages to process per second before triggering a "pause". Higher values allow for greater
   *   processing velosity / messages per second.
   */
  protected function throttle($maxMessageRate) {

    $this->throttleMessageCount++;

    // Reset the number of processed message by each second
    if ($this->throttleSecondStamp != date('s')) {
      $this->throttleSecondStamp = date('s');
      $this->throttleMessageCount = 0;
    }

    // Trigger processing delay when max message rate is exceeded
    if ($this->throttleMessageCount > $maxMessageRate) {
      sleep(MBC_BaseConsumer::THROTTLE_TIMEOUT);

      echo '- Trottling activated, message rate: ' . $this->throttleMessageCount . '. Waiting for ' . MBC_BaseConsumer::THROTTLE_TIMEOUT . ' seconds.', PHP_EOL;
      $this->throttleMessageCount = 0;
    }
  }

  /**
   * queueStatus(): Lookup the current message ready and unacked count of the queue that the
   * applicaiton is connected to.
   *
   * @param string $targetQueue
   *   The name of the queue to gather the stats from.
   *
   * return array $messageCount
   *   The numnber of messages in the "ready" and "unacked" state.
   */
  protected function queueStatus($targetQueue) {

    $queueStatus = $this->mbRabbitMQManagementAPI->queueStatus($targetQueue);
    return $queueStatus;
  }

  /**
   * Sets values for processing based on contents of message from consumed queue.
   *
   * @param array $message
   *  The payload of the unseralized message being processed.
   */
  abstract protected function setter($message);

  /**
   * Evalue message settings to determine if the message can be processed.
   */
  abstract protected function canProcess();

  /**
   * Process message from consumed queue.
   */
  abstract protected function process();

}
