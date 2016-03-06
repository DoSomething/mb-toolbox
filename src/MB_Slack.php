<?php
/**
 * Base class for classes within the Message Broker system to extend. Focused on consuming
 * messages from queues a RabbitMQ server.
 */

namespace DoSomething\MB_Toolbox;

use DoSomething\MB_Toolbox\MB_Configuration;
use Maknz\Slack\Client as Client;
use \Exception;

/*
 * MB_Toolbox_Slack():
 */
abstract class MB_Toolbox_Slack
{

  /**
   * Singleton instance of MB_Configuration application settings and service objects
   * @var object $mbConfig
   */
  protected $mbConfig;

  /**
   * Slack connection
   * @var object $slack
   */
  protected $slack;

  /**
   * StatHat object for logging of activity
   * @var object $statHat
   */
  protected $statHat;

  /**
   * Constructor for MBC_Slack - 
   */
  public function __construct() {

    $this->mbConfig = MB_Configuration::getInstance();

    $this->slackConfig = $this->mbConfig->getProperty('slack');
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->init();
  }

  /**
   *
   * @param 
   */
  protected function init() {

    // Set in incoming webhooks configuration in Slack, here for reference
    // https://dosomething.slack.com/services/24691590965
    $settings = [
      'channel' => '#message-broker',
      'username' => 'pris',
      'icon' => 'http://img11.deviantart.net/cabe/i/2014/155/c/1/pris_of_blade_runner_by_legoras-d7l1wzz.jpg'
    ];
    $this->slack = new Client($this->slackConfig['webhookURL']);
  }
  
  /**
   *
   * @param string $message
   * 
   */
  public function alert($to = '@dee', $message = 'Missing message') {

    $this->slack->to($to)->send($message);
  }

}
