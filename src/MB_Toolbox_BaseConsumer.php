<?php

namespace DoSomething\MB_Toolbox;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

/*
 * MBC_UserAPICampaignActivity.class.in: Used to process the transactionalQueue
 * entries that match the campaign.*.* binding.
 */
abstract class MB_Toolbox_BaseConsumer
{

   // The number of seconds to pause when throttling is triggered
   const THROTTLE_TIMEOUT = 5;

  /**
   * Singleton instance of MB_Configuration application settings and service objects
   *
   * @var object
   */
  protected $mbConfig;

  /**
   * Message Broker connection to RabbitMQ
   *
   * @var object
   */
  protected $messageBroker;

  /**
   * StatHat object for logging of activity
   *
   * @var object
   */
  protected $statHat;

  /**
   * Message Broker Toolbox - collection of utility methods used by many of the
   * Message Broker producer and consumer applications.
   *
   * @var object
   */
  protected $toolbox;

  /**
   * Value of message from queue to be consumed / processed.
   *
   * @var array
   */
  protected $message;

  /**
   * The number of messages that have been processed. Used to calculate the message rate
   * to trigger throttling.
   *
   * @var integer
   */
  protected $throttleMessageCount = 0;

   /**
   * A second value to track the lapsed time to calculate the massage rate.
   *
   * @var integer
   */
  protected $throttleSecondStamp = NULL;

  /**
   * Constructor for MBC_BaseConsumer - all consumer applications should extend this base class.
   *
   * @param object $messageBroker
   *   The Message Broker object used to interface the RabbitMQ server exchanges and related queues.
   *  
   * @param object $statHat
   *   Track application activity by triggering counters in StatHat service.
   *
   * @param object $toolbox
   *   A collection of common tools for the Message Broker system.
   *   
   * @param array $settings
   *   Settings from internal and external services used by the application.
   */
  public function __construct() {

    $this->mbConfig = MB_Configuration::getInstance();
    $this->messageBroker = $this->mbConfig->getProperty('messageBroker');
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->toolbox = $this->mbConfig->getProperty('mbToolbox');
  }

  /**
   * Initial method triggered by blocked call in base mbc-??-??.php file. The $payload is the
   * contents of the message being processed from the queue.
   *
   * @param array $payload
   *   The contents of the queue entry
   */
  public function consumeQueue($payload) {

    $this->message = unserialize($payload->body);
    $this->message['original'] = $this->message;
    $this->message['payload'] = $payload;
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
