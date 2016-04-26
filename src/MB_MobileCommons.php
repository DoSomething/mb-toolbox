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
  * @param array $user
  *   Settings of user account to check against.
  * @param string $target
  *   The type of account to check
  */
  public function checkExisting($user, &$existingStatus) {

    $mobilecommonsStatus = (array) $this->mobileCommons->profiles_get(array('phone_number' => $user['mobile']));
    if (!isset($mobilecommonsStatus['error'])) {
      echo($user['mobile'] . ' already a Mobile Commons user.' . PHP_EOL);
      if (isset($mobilecommonsStatus['profile']->status)) {
        $existingStatus['mobile-error'] = (string)$mobilecommonsStatus['profile']->status;
        $this->statHat->ezCount('mbc-user-import: MBC_UserImport_Toolbox: checkExistingSMS: ' . $existingStatus['mobile-error'], 1);
        // opted_out_source
        $existingStatus['mobile-acquired'] = (string)$mobilecommonsStatus['profile']->created_at;
      }
      else {
        $existingStatus['mobile-error'] = 'Existing account';
        $this->statHat->ezCount('mbc-user-import: MBC_UserImport_Toolbox: checkExistingSMS: Existing account', 1);
      }
      $existingStatus['mobile'] = $user['mobile'];
    }
    else {
      $mobileCommonsError = $mobilecommonsStatus['error']->attributes()->{'message'};
      // via Mobile Commons API - "Invalid phone number" aka "number not found", the number is not from an existing user.
      if (!$mobileCommonsError == 'Invalid phone number') {
        echo 'Mobile Common Error: ' . $mobileCommonsError, PHP_EOL;
        $this->statHat->ezCount('mbc-user-import: MBC_UserImport_Toolbox: checkExistingSMS: Invalid phone number', 1);
      }
    }

  }

}
