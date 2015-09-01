<?php
/*
 * Message Broker ("MB") Configuration class library of functionality related to
 * configuration settings within the Message Broker system.
 */

namespace DoSomething\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;

/**
 * MB_Configuration class - application-level configuration the Message Broker
 * system uses. Settings contained by the single instance is specific to the application and accessible
 * to all application classes.
 *
 * The class uses the Singleton pattern. This ensures there is only one instance of the application
 * settings.
 */
class MB_Configuration
{

  /**
   * All Message Broker configuration settings - the source of truth.
   *
   * @var array
   */
  private $configSettings = [];

  /**
   * Instance of MB_Configuration class. Private and static to ensure access is only internal.
   */
  private static $instance;

  /**
   * Constructor - private to enforce singleton pattern. Only once instance of class allowed.
   *
   * Protected constructor to prevent creating a new instance of the *Singleton* via the
   * `new` operator from outside of the class.
   *
   * See: http://www.phptherightway.com/pages/Design-Patterns.html
   */
  private function __construct() {}

  /**
   * Private clone method to prevent cloning of the instance of the
   * *Singleton* instance.
   *
   * The magic method __clone() is declared as private to prevent cloning of an instance
   * of the class via the clone operator.
   */
  private function __clone() {}

  /**
   * Static method to limit instantiation of class to only one object.
   */
  public static function getInstance() {
    if (empty(self::$instance)) {
      self::$instance = new MB_Configuration();
    }
    return self::$instance;
  }

  /**
   * Set property in MB_Configuration instance.
   *
   * @todo: Add locking to prevent adding / editing of settings after addition of configuration
   * settings is complete.
   *
   * @param string $key
   *   The name of the property.
   * @param mixed $value
   *   The value to store in the instance configSettings array object property.
   */
  public function setProperty($key, $value) {
    $this->configSettings[$key] = $value;
  }

  /**
   * Get property in MB_Configuration instance.
   *
   * @param string $key
   *   The name of property to get.
   * @param boolean $notifyWarnings
   *   Flag to enable / disable warning and traceback if property not found.
   */
  public function getProperty($key, $notifyWarnings = TRUE) {
    if (!isset($this->configSettings[$key]) && $notifyWarnings) {
      echo 'MB_Configuration->getProperty() - Warning: "' . $key . '" not defined.', PHP_EOL;
      $callers = debug_backtrace();
      echo '- Called from: ' . $callers[1]['function'], PHP_EOL;
      return FALSE;
    }
    return $this->configSettings[$key];
  }

  /**
   * Construct RabbitMQ config for connection to exchange and queue.
   *
   * @param string $targetExchange
   *   The name of the exchange to include in the construction of $config
   * @param string $targetQueues
   *   The name of the queue(s) to include in the construction of $config
   *
   * @return array $config
   *   All of the Message Broker configuration settings to make a connection to RabbitMQ.
   */
  public function constructRabbitConfig($targetExchange, $targetQueues = NULL) {

    self::setProperty('configFile', self::_gatherSettings(CONFIG_PATH . '/mb_config.json'));
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
        if (isset($exchangeSettings->queues->$queue->name)) {
          $config['queue'][] = array(
            'name' => $exchangeSettings->queues->$queue->name,
            'passive' => $exchangeSettings->queues->$queue->passive,
            'durable' =>  $exchangeSettings->queues->$queue->durable,
            'exclusive' =>  $exchangeSettings->queues->$queue->exclusive,
            'auto_delete' =>  $exchangeSettings->queues->$queue->auto_delete,
            'bindingKey' => $exchangeSettings->queues->$queue->binding_key,
          );
          if (isset($exchangeSettings->queues->$queue->consume)) {
            $config['consume'] = array(
              'no_local' => $exchangeSettings->queues->$queue->consume->no_local,
              'no_ack' => $exchangeSettings->queues->$queue->consume->no_ack,
              'nowait' => $exchangeSettings->queues->$queue->consume->nowait,
              'exclusive' => $exchangeSettings->queues->$queue->consume->exclusive,
            );
          }
        }
        else {
          echo 'MB_Configuration->constructRabbitConfig(): Error - ' . $queue . ' settings not found.', PHP_EOL;
        }
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
   *   The exchange settings in the format needed for a RabbitMQ connection.
   */
  public function exchangeSettings($targetExchange) {

    $settings = NULL;
    if (isset($this->configSettings['configFile']->rabbit->exchanges)) {
      foreach($this->configSettings['configFile']->rabbit->exchanges as $exchange => $exchangeSettings) {
        if ($exchange == $targetExchange) {
          $settings = $exchangeSettings;
        }
      }
    }
    else {
      echo 'Error - No exchange settings found.', PHP_EOL;
    }

    // Trap exchange not found
    if ($settings == NULL) {
      echo 'MB_Configuration->exchangeSettings(): Error - ' . $targetExchange . ' not found in config settings.';
      exit;
    }

    return $settings;
  }

  /*
   * gatherSettings(): Load "settings" section of mb_config.json into setting accessable by
   * MB_Configuration methods.
   *
   * @param string $targetSetting
   *   Request value of specific setting.
   */
  public function gatherSettings($targetSetting) {

    // Load settings if not already available
    $config = self::getProperty('settings', FALSE);
    if (!($config)) {
      $config = self::_gatherSettings(CONFIG_PATH . '/mb_config.json');
      self::setProperty('settings', $config->settings);
    }

    // Optional, method can simply store settings values
    $foundSetting = NULL;
    if ($targetSetting != NULL) {
      if (isset($config->settings->$targetSetting)) {
        $foundSetting = $config->settings->$targetSetting;
        unset($foundSetting->__comment);
      }
      else {
        $foundSetting = NULL;
      }
    }

    return $foundSetting;
  }

  /**
   * Gather all Message Broker configuration settings from the defined source.
   *
   * @param string $source
   *   Source can be the path to a file or a URL to an endpoint.
   */
  private static function _gatherSettings($source) {

    if (strpos('http://', $source) !== FALSE) {
      echo 'cURL sources are not currently supported.', PHP_EOL;
    }
    elseif (file_exists($source)) {
        $settings = json_decode(implode(file($source)));
        return $settings;
    }
    else {
      echo 'Source: ' . $source . ' not found.', PHP_EOL;
    }

  }

}
