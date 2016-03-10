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
class MB_Slack
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

    $this->slackConfig = $this->mbConfig->getProperty('slack_config');
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->init();
  }

  /**
   * Create Slack object connection.
   */
  protected function init() {

    // Set in incoming webhooks configuration in Slack, here for reference
    // https://dosomething.slack.com/services/24691590965
    $settings = [
      'channel' => '#message-broker',
      'username' => 'Pris',
      'author_icon' => 'http://img11.deviantart.net/cabe/i/2014/155/c/1/pris_of_blade_runner_by_legoras-d7l1wzz.jpg',
      'link_names' => true,
      'allow_markdown' => true
    ];
    $this->slack = new Client($this->slackConfig['webhookURL']);
  }
  
  /**
   * Send report to Slack.
   *
   * $param string $to
   *   Comma separated list of Slack channels ("#") and/or users ("@") to send message to.
   * @param string $message
   * 'Array of message attachment options. https://github.com/maknz/slack#send-an-attachment-with-fields
   * 
   */
  public function alert($to = '@dee', $message = []) {

    $this->slack->to($to)->attach($message)->send();
  }

}
