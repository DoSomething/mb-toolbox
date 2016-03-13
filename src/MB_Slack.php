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
   * Slack connection keys
   * @var object $slackConfig
   */
  protected $slackConfig;

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

    $this->slackConfig = $this->mbConfig->getProperty('slack_config');
    $this->statHat = $this->mbConfig->getProperty('statHat');
  }

  /**
   *
   */
  private function lookupChannelKey($channelName) {

    if ($channelName == 'niche_monitoring') {
      $channelKey = $this->slackConfig['webhookURL_niche_monitoring'];
    }
    elseif ($channelName == 'after-school-internal') {
      $channelKey = $this->slackConfig['webhookURL_after-school-internal'];
    }
    else {
      $channelKey = $this->slackConfig['webhookURL_message-broker'];
    }

    return $channelKey;
  }
  
  /**
   * Send report to Slack.
   *
   * $param string $to
   *   Comma separated list of Slack channels ("#") and/or users ("@") to send message to.
   * @param string $message
   *   List of message attachment options. https://github.com/maknz/slack#send-an-attachment-with-fields
   * @param string $channelNames
   *   The name of the channel to send the message to.
   */
  public function alert($to = '@dee', $message = [], $channelNames) {

    foreach ($channelNames as $channelName) {
      $channelKey = $this->lookupChannelKey($channelName);
      $slack = new Client($channelKey);
      $slack->to($to)->attach($message)->send();
    }
  }

}
