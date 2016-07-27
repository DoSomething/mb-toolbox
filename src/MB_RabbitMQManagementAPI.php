<?php

namespace DoSomething\MB_Toolbox;

use RabbitMq\ManagementApi\Client as RabbitMqManagementApi;
use DoSomething\StatHat\Client as StatHat;
use \Exception;

/**
 * MBC_UserRegistration class - functionality related to the Message Broker
 * consumer mbc-registration-email.
 */
class MB_RabbitMQManagementAPI
{

  /**
   * Collection of secret connection settings.
   * @var array
   */
  private $credentials;
  
  /**
   * RabbitMQ Management API.
   * @var array
   */
  private $rabbitManagement;
  
  /**
   * Target vertual host defined in RabbitMQ server.
   * @var string $vhost
   */
  private $vhost;
  
  /**
   * Singleton instance of MB_Configuration application settings and service objects
   *
   * @var object
   */
  private $mbConfig;

  /**
   * Setting from external service to track activity - StatHat.
   *
   * @var object
   */
  private $statHat;

  /**
   * Constructor for MB_RabbitMQManagementAPI
   *
   * @param array $config
   *   Configuration settings from mb-config.inc
   */
  public function __construct($conig) {

    $domain = 'http://' . $conig['domain'];
    $port = $conig['port'];
    $vhost = $conig['vhost'];
    $this->vhost = $vhost;
    $username = $conig['username'];
    $password = $conig['password'];

    if ($port > 0 && is_numeric($port)) {
      $domain .= ':' . (int) $port;
    }

    $this->rabbitManagement = new RabbitMqManagementApi(
      NULL,
      $domain,
      $username,
      $password
    );
    
    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
  }

  /**
   * Gather queue status numbers: ready and unacked.
   *
   * @param string $queueName
   *   The name of the queue to return stats for.
   *
   * @return array $queueStats
   *   The current queue ready and unacked values.
   */
  public function queueStatus($queueName) {

    $queue = $this->rabbitManagement->queues()->get($this->vhost, $queueName);

    $queueStats['ready'] = $queue['messages_ready'];
    $queueStats['unacked'] = $queue['messages_unacknowledged'];
    
      $this->statHat->ezCount('MB_Toolbox: MB_RabbitMQManagementAPI: queueStatus', 1);

    return $queueStats;
  }

}
