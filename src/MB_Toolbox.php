<?php
/*
 * Message Broker ("MB") toolbox class library
 */

class MB_Toolbox
{

  /**
   * Setting from external service to track activity - StatHat.
   *
   * @var object
   */
  private $statHat;

  /**
   * Constructor
   *
   * @param array $config
   *   Connection and configuration settings common to the application
   *
   * @return object
   */
  public function __construct($config = array()) {
    $this->statHat = new StatHat($settings['stathat_ez_key'], 'MB_Toolbox:');
    $this->statHat->setIsProduction(FALSE);
  }
 
}
