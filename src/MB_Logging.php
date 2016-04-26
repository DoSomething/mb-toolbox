 <?php
/**
 * Base class for classes within the Message Broker system to extend. Focused on consuming
 * messages from queues a RabbitMQ server.
 */

namespace DoSomething\MB_Logging;

use DoSomething\MB_Toolbox\MB_Configuration;
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
   * Constructor for MBC_Logging - gather config settings.
   */
  public function __construct() {

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
  */
  public function logExisting($existing, $importUser) {
    
    if (isset($existing['email']) ||
        isset($existing['drupal-uid']) ||
        isset($existing['mobile'])) {

      $existing['origin'] = [
        'name' => $importUser['origin'],
        'processed' => time()
      ];
      $payload = serialize($existing);
      $this->mbLogging->publishMessage($payload);
      $this->statHat->ezCount('mbc-user-import: MBC_UserImport_Toolbox: logExisting', 1);
    }
  }
  
}
