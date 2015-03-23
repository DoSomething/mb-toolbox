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
  public function __construct($source, $applicationSettings) {

    $this->configSettings = $this->_gatherSettings($source);

    $this->statHat = new StatHat($applicationSettings['stathat_ez_key'], 'MC_Configuration:');
    $this->statHat->setIsProduction(TRUE);
  }
  
  /**
   * Gather all setting for a specific exchange
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
