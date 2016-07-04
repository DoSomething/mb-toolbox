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
   * Message Broker connection to RabbitMQ for Dead Letter messages.
   *
   * @var object
   */
  protected $messageBroker_deadLetter;

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
   * @param string $targetMBconfig The Message Broker object used to interface the RabbitMQ server
   *                               exchanges and related queues.
   */
  public function __construct($targetMBconfig = 'messageBroker') {

    $this->mbConfig = MB_Configuration::getInstance();

    $this->settings = $this->mbConfig->getProperty('generalSettings');
    $this->messageBroker = $this->mbConfig->getProperty($targetMBconfig);
    $this->messageBroker_deadLetter = $this->mbConfig->getProperty('messageBroker_deadLetter');
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
   *
   * @return null
   *
   * @throws Exception
   */
  protected function consumeQueue($payload) {

    $message = $payload->body;
    try {

      if ($this->isSerialized($message)) {
        $this->message = unserialize($message);
      }
      else {
        $this->message = json_decode($message, true);
      }
      $this->message['original'] = $this->message;
      $this->message['payload'] = $payload;
    }
    catch(Exception $e) {
      echo 'MB_Toolbox_BaseConsumer: Error processing payload->body: consumeQueue(): ' . $e->getMessage();
    }

  }

  /**
   * Detect if the message is in seralized format.
   *
   * Originally the Message Broker system used seralization to format messages as all of the producers and
   * consumers are PHP based applications. To support microservices in other languages a more genaral JSON
   * format is being used for message formatting.
   *
   * @param string $message
   *
   * @return string
   */
  protected function isSerialized($message) {
    return ($message == serialize(false) || @unserialize($message) !== false);
  }

  /*
   * logConsumption(): Log the status of processing a specific message element.
   *
   * @param array $targetNames
   *
   * @return null
   */
  protected function logConsumption($targetNames = null) {

    if ($targetNames != null && is_array($targetNames)) {

      echo '** Consuming ';
      $targetNameFound = false;
      foreach ($targetNames as $targetName) {
        if (isset($this->message[$targetName])) {
          if ($targetNameFound) {
             echo ', ';
          }
          echo $targetName . ': ' . $this->message[$targetName];
          $targetNameFound = true;
        }
      }
      if ($targetNameFound) {
        echo ' from: ' .  $this->message['user_country'] . ' doing: ' . $this->message['activity'], PHP_EOL;
      }
      else {
        echo 'xx Target property not found in message.', PHP_EOL;
      }

    } else {
      echo 'Target names: ' . print_r($targetNames, true) . ' are not defined.', PHP_EOL;
    }
  }

  /**
   * Log payload with RabbitMQ objects removed for clarity.
   *
   * @return null
   */
  protected function reportErrorPayload() {

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
    *
    * @return null
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
   * @return array $messageCount
   *   The numnber of messages in the "ready" and "unacked" state.
   */
  protected function queueStatus($targetQueue) {

    $queueStatus = $this->mbRabbitMQManagementAPI->queueStatus($targetQueue);
    echo '- ' . $targetQueue . ' ready: ' . $queueStatus['ready'], PHP_EOL;
    echo '- ' . $targetQueue . ' unacked: ' . $queueStatus['unacked'], PHP_EOL;

    return $queueStatus;
  }

  /**
   * deadLetter() - send message and related error to queue. Allows processing queues to be unblocked
   * and log problem messages with details of the error resulting from the message.
   *
   * @param String $message The error message that triggered sending message to deadLetter queue.
   * @param String $location Where the event took place.
   * @param String $lerror The error message related to sending the message.
   *
   * @return null
   */
  public function deadLetter($message, $location, $error) {

    $message['incidentDate'] = date(DATE_RFC2822);
    $message['location'] = $location;
    $message['error'] = $error;
    $message = json_encode($message);
    $this->messageBroker_deadLetter->publish($message, 'deadLetter');
  }

  /**
   * Sets values for processing based on contents of message from consumed queue.
   *
   * @param array $message The payload of the unseralized message being processed.
   *
   * @return null
   */
  abstract protected function setter($message);

  /**
   * Evalue message settings to determine if the message can be processed.
   *
   * @param array $message The payload of the unseralized message being processed.
   *
   * @return null
   */
  abstract protected function canProcess($message);

  /**
   * Process message from consumed queue.
   *
   * @param array $params Values used by the processing logic.
   *
   * @return null
   */
  abstract protected function process($params);

}
