<?php
/**
 * Base class for classes within the Message Broker system to extend. Focused on consuming
 * messages from queues a RabbitMQ server.
 */

namespace DoSomething\MB_Toolbox;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use \Exception;

/*
 * MB_Logging():
 */
class MB_Logging
{

  /**
   * Singleton instance of MB_Configuration application settings and service objects
   * @var object $mbConfig
   */
  protected $mbConfig;

  /**
   * Setting from external service to track activity - StatHat.
   *
   * @var object
   */
  private $statHat;

  /**
   * Constructor for MBC_Logging - gather config settings.
   */
  public function __construct()
  {

    $this->mbConfig = MB_Configuration::getInstance();

    $this->mbLogging = $mbConfig->getProperty('mbLogging');
    $this->statHat = $this->mbConfig->getProperty('statHat');
  }

  /**
   * Check for the existence of email (Mailchimp) and SMS (Mobile Commons)
   * accounts.
   *
   * @param array $existing
   *   Values to submit for existing user log entry.
   * @param string $origin
   *   The name of the application that wants to log the existing user.
   */
  public function logExisting($existing, $origin)
  {

    if (isset($existing['email']) ||
    isset($existing['drupal-uid']) ||
    isset($existing['mobile'])) {
      $existing['origin'] = [
        'name' => $origin,
        'processed' => time()
      ];
      $payload = serialize($existing);
      $this->mbLogging->publishMessage($payload);
      $this->statHat->ezCount('MB_Toolbox: MB_Logging: logExisting', 1);
    }
  }
}
