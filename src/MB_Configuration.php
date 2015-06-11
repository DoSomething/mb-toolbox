<?php
/*
 * Message Broker ("MB") Configuration class library of functionality related to
 * configuration settings within the Message Broker system.
 */

namespace DoSomething\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;

/**
 * MB_Configuration class - functionality related to the Message Broker
 * configuration.
 */
class MB_Configuration
{
  /**
   * Report consumer activity to StatHat service.
   *
   * @var object
   */
  private $statHat;
  
  /**
   * All Message Broker configuration settings - the source of truth.
   *
   * @var object
   */
  private $configSettings;

  /**
   * Constructor for MessageBroker-Config
   *
   * @param array $source
   *   The source of configuration settings. This can be from a file or an
   *   endpoint.
   * @param array $applicationSettings
   *   General application settings for use by all classes in application.
   */
  public function __construct($settings, $configSettingPath) {

    $this->statHat = new StatHat($settings['stathat_ez_key'], 'MC_Configuration:');
    $this->statHat->setIsProduction(TRUE);

    $this->configSettings = $this->_gatherSettings($configSettingPath);
  }

  /**
   * Construct config for connection to Rabbit exchange and queue
   *
   * @param string $targetExchange
   *   The name of the exchange to include in the construction of $config
   * @param string $targetQueues
   *   The name of the queue(s) to include in the construction of $config
   *
   * @return array $config
   *   All of the Message Broker configuration settings to make a connection to RabbitMQ.
   */
  public function constructConfig($targetExchange, $targetQueues = NULL) {

    $exchangeSettings = $this->exchangeSettings($targetExchange);

    $config['exchange'] = array(
      'name' => $exchangeSettings->name,
      'type' => $exchangeSettings->type,
      'passive' => $exchangeSettings->passive,
      'durable' => $exchangeSettings->durable,
      'auto_delete' => $exchangeSettings->auto_delete,
    );

    if ($config['exchange']['type'] == "topic") {
      foreach ($exchangeSettings->queues as $queueSetting) {
        if (in_array($queueSetting->name, $targetQueues) || $targetQueues == NULL) {
          foreach ($queueSetting->binding_patterns as $bindingKey) {
            $config['queue'][] = array(
              'name' => $queueSetting->name,
              'passive' => $queueSetting->passive,
              'durable' =>  $queueSetting->durable,
              'exclusive' =>  $queueSetting->exclusive,
              'auto_delete' =>  $queueSetting->auto_delete,
              'bindingKey' => $bindingKey,
            );
          }
        }
      }
    }
    else {
      foreach ($targetQueues as $queue) {
        $config['queue'][] = array(
          'name' => $exchangeSettings->queues->$queue->name,
          'passive' => $exchangeSettings->queues->$queue->passive,
          'durable' =>  $exchangeSettings->queues->$queue->durable,
          'exclusive' =>  $exchangeSettings->queues->$queue->exclusive,
          'auto_delete' =>  $exchangeSettings->queues->$queue->auto_delete,
          'bindingKey' => $exchangeSettings->queues->$queue->binding_key,
        );
      }
    }

    return $config;
  }
  
  /**
   * Gather all setting for a specific exchange
   *
   * @param string $targetExchange
   *   The name of the exchange to gather setting for.
   *
   * @return array $settings
   *   The exchange settings in the format needed for a RabbitMQ conneciton.
   */
  public function exchangeSettings($targetExchange) {
    
    if (isset($this->configSettings->rabbit->exchanges)) {
      foreach($this->configSettings->rabbit->exchanges as $exchange => $exchangeSettings) {
        if ($exchange == $targetExchange) {
          $settings = $exchangeSettings;
        }
      }
    }
    else {
      echo 'Error - No exchange settings found.', PHP_EOL;
    }

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('exchangeSettings');
    $this->statHat->reportCount(1);

    return $settings;
  }
  
  /**
   * Gather all Message Broker configuration settings from the defined source.
   *
   * @param string $source
   *   Source can be the path to a file or a URL to an endpoint.
   */
  private function _gatherSettings($source) {

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('_gatherSettings');
    $this->statHat->reportCount(1);

    if (strpos('http://', $source) !== FALSE) {
      echo 'cURL sources are not currently supported.', PHP_EOL;
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('_gatherSettings - cURL sources not supported');
      $this->statHat->reportCount(1);
    }
    elseif (file_exists($source)) {
        $settings = json_decode(implode(file($source)));
        return $settings;
    }
    else {
      echo 'Source: ' . $source . ' not found.', PHP_EOL;
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('_gatherSettings - source not found');
      $this->statHat->reportCount(1);
    }

  }
  
}
