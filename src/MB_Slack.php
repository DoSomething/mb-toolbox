<?php
/**
 * MB_Slack class - functionality related to the RabbitMQ Managment API:
 * https://api.slack.com/
 */

namespace DoSomething\MB_Toolbox;

use DoSomething\MB_Toolbox\MB_Configuration;
use Maknz\Slack\Client as Client;
use DoSomething\StatHat\Client as StatHat;
use \Exception;

/**
 * Class MB_Slack
 *
 * @package DoSomething\MB_Toolbox
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
  public function __construct()
  {

    $this->mbConfig = MB_Configuration::getInstance();

    $this->slackConfig = $this->mbConfig->getProperty('slack_config');
    $this->statHat = $this->mbConfig->getProperty('statHat');
  }

  /**
   * Lookup channel key value based on channel name.
   *
   * @param string $channelName The name of the channel.
   *
  * @return string $channelKey A key value based on the channel name.
   */
  private function lookupChannelKey($channelName)
  {

    if ($channelName == '#niche_monitoring') {
      $channelKey = $this->slackConfig['webhookURL_niche_monitoring'];
    } elseif ($channelName == '#after-school-internal') {
      $channelKey = $this->slackConfig['webhookURL_after-school-internal'];
    } else {
      $channelKey = $this->slackConfig['webhookURL_message-broker'];
    }

    return $channelKey;
  }

  /**
   * Send report to Slack.
   *
   * $param array $to
   *   Comma separated list of Slack channels ("#") and/or users ("@") to send message to.
   * @param string $message
   *   List of message attachment options. https://github.com/maknz/slack#send-an-attachment-with-fields
   * @param array $channelNames
   *   The name of the channel to send the message to.
   */
  public function alert($channelNames, $message, $tos = ['@dee'])
  {

    $to = implode(',', $tos);
    foreach ($channelNames as $channelName) {
      $channelKey = $this->lookupChannelKey($channelName);
      $slack = new Client($channelKey);
      $slack->to($to)->attach($message)->send();
      $this->statHat->ezCount('MB_Toolbox: MB_Slack: alert', 1);
    }
  }
}
