<?php
/**
 * Base class for classes within the Message Broker system to extend. Focused on consuming
 * messages from queues a RabbitMQ server.
 */

namespace DoSomething\MB_MobileCommons;

use DoSomething\MB_Toolbox\MB_Configuration;
use \Exception;

/*
 * MB_Toolbox_Slack():
 */
class MB_MobileCommons
{

  /**
   * Singleton instance of MB_Configuration application settings and service objects
   * @var object $mbConfig
   */
  protected $mbConfig;
  
  /**
   * Mobile Commons DoSomething.org US connection.
   *
   * @var object $mobileCommons
   */
  private $moblieCommons;

  /**
   * StatHat object for logging of activity
   * @var object $statHat
   */
  protected $statHat;

  /**
   * Constructor for MBC_Slack - gather config settings.
   */
  public function __construct() {

    $this->mbConfig = MB_Configuration::getInstance();

    $this->mobileCommons = $mbConfig->getProperty('mobileCommons');
    $this->statHat = $this->mbConfig->getProperty('statHat');
  }

 /**
  * Check for the existence of SMS (Mobile Commons) account.
  *
  * @param array $mobile
  *   The mobile number to check for existing account.
  *
  * @return string $existingStatus
  *   Details of existing account or boolean false.
  */
  public function checkExisting($mobile) {

    $mobileCommonsStatus = (array) $this->mobileCommons->profiles_get(array('phone_number' => $mobile));
    if (!isset($mobileCommonsStatus['error'])) {
      echo($mobile . ' already a Mobile Commons user.' . PHP_EOL);
      if (isset($mobileCommonsStatus['profile']->status)) {
        $existingStatus['mobile-error'] = (string)$mobileCommonsStatus['profile']->status;
        $this->statHat->ezCount('MB_Toolbox: MB_MobileCommons checkExisting: ' . $existingStatus['mobile-error'], 1);
        // opted_out_source
        $existingStatus['mobile-acquired'] = (string)$mobileCommonsStatus['profile']->created_at;
      }
      else {
        $existingStatus['mobile-error'] = 'Existing account';
        $this->statHat->ezCount('MB_Toolbox: MB_MobileCommons checkExisting: Existing account', 1);
      }
      $existingStatus['mobile'] = $mobile;
      return $existingStatus;
    }
    else {
      $mobileCommonsError = $mobileCommonsStatus['error']->attributes()->{'message'};
      // via Mobile Commons API - "Invalid phone number" aka "number not found", the number is not from an existing user.
      if ($mobileCommonsError == 'Invalid phone number') {
        $this->statHat->ezCount('MB_Toolbox: MB_MobileCommons checkExisting: New number', 1);
        return false;
      }
    }

  }

}
