<?php
/*
 * Message Broker ("MB") MailChimp class library of functionality related to
 * the MailChimp service: https://apidocs.mailchimp.com/api/2.0/
 */

namespace DoSomething\MB_Toolbox;

use \Mailchimp\MailchimpLists;
use DoSomething\StatHat\Client as StatHat;
use \Exception;

/**
 * Class MB_MailChimp
 *
 * @package DoSomething\MB_Toolbox
 */
class MB_MailChimp
{

  /**
   * External service MailChimp used to administer and send to emailing lists.
   *
   * @var object
   */
  private $mailchimpLists;

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
   * Constructor for MBC_Mailchimp
   *
   * @param array $apiKey
   *   The MailChimp API key to use for this instance of MailChip API activities.
   */
  public function __construct($apiKey)
  {
    $this->mailchimpLists = new MailchimpLists($apiKey);

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
  }

  /**
   * Make single signup submission to MailChimp. Typically used for resubscribes.
   *
   * @param string $listId
   *   A unique ID that defines what MailChimp list the batch should be added to
   * @param array $composedItem
   *   The the details of an email address to be submitted to MailChimp
   *
   * @return array
   *   A list of the RabbitMQ queue entry IDs that have been successfully
   *   submitted to MailChimp.
   */
  public function submitSubscribe($listId, $composedItem = [])
  {

    $results = $this->mailChimp->call("lists/subscribe",
      [
        'id' => $listID,
        'email' => [
          'email' => $composedItem['email']['email']
        ],
        'merge_vars' => $composedItem['merge_vars'],
        'double_optin' => false,
        'update_existing' => true,
        'replace_interests' => false,
        'send_welcome' => false,
      ]);

    // Trap errors
    if (isset($results['error'])) {
      throw new Exception('Call to lists/subscribe returned error response: ' . $results['name'] . ': ' .  $results['error']);
    } elseif ($results == 0) {
      throw new Exception('Hmmm: No results returned from Mailchimp lists/subscribe submission.');
    }

    $this->statHat->ezCount('MB_Toolbox: MB_MailChimp: submitSubscribe', 1);

    return $results;
  }

  /**
   * Format email list to meet MailChimp API requirements for batchSubscribe
   *
   * @param string $listId
   *   Unique ID that defines what MailChimp list the batch should be added to
   * @param array $subscribers
   *   The list of users to be processed
   *
   * @return array
   *   Array of email addresses formatted to meet MailChimp API requirements.
   */
  public function addSubscribers($listId, array $subscribers)
  {
    foreach ($subscribers as $subscriber) {
      if (isset($subscriber['birthdate']) && is_int($subscriber['birthdate'])) {
        $subscriber['birthdate_timestamp'] = $subscriber['birthdate'];
      }
      if (isset($subscriber['mobile']) && strlen($subscriber['mobile']) < 8) {
        unset($subscriber['mobile']);
      }

      // support different merge_vars for US vs UK
      if (isset($subscriber['application_id']) && $subscriber['application_id'] == 'UK') {
        $mergeVars = [
          'FNAME' => isset($subscriber['fname']) ? $subscriber['fname'] : '',
          'LNAME' => isset($subscriber['lname']) ? $subscriber['lname'] : '',
          'MERGE3' => isset($subscriber['birthdate_timestamp']) ? date('d/m/Y',
            $subscriber['birthdate_timestamp']) : '',
        ];
      } // Don't add Canadian users to MailChimp
      elseif (isset($subscriber['application_id']) && $subscriber['application_id'] == 'CA') {
        $this->channel->basic_ack($subscriber['mb_delivery_tag']);
        break;
      } else {
        $mergeVars = [
          'UID' => isset($subscriber['uid']) ? $subscriber['uid'] : '',
          'FNAME' => isset($subscriber['fname']) ? $subscriber['fname'] : '',
          'MERGE3' => (isset($subscriber['fname']) && isset($subscriber['lname'])) ?
            $subscriber['fname'] . $subscriber['lname'] : '',
          'BDAY' => isset($subscriber['birthdate_timestamp']) ?
            date('m/d', $subscriber['birthdate_timestamp']) : '',
          'BDAYFULL' => isset($subscriber['birthdate_timestamp']) ?
            date('m/d/Y', $subscriber['birthdate_timestamp']) : '',
          'MOBILE' => isset($subscriber['mobile']) ? $subscriber['mobile'] : '',
        ];
      }

      // Assign source interest group. Only support on main DoSomething Members List
      // @todo: Support "id" as a variable. Perhaps a config setting keyed on countries / global.
      if (isset($subscriber['source']) &&
        isset($subscriber['user_country']) &&
        strtoupper($subscriber['user_country']) == 'US')
      {
        // $mergeVars['groupings'] = [
        //   0 => [
        //     'id' => 10657,  // DoSomething Memebers -> Import Source
        //     'groups' => [
        //       $newSubscriber['source']
        //     ]
        //   ],
        // ];
      }

      $parameters = [
        // 'status' => 'subscribed',
        'merge_vars' => $mergeVars,
      ];

      $this->mailchimpLists->addOrUpdateMember($listId, $subscriber['email'], $parameters, true);
    }

  }

  /**
   * Make batch signup submission to MailChimp list: lists/batch-subscribe
   *
   * @param string $listId
   *   A unique ID that defines what MailChimp list the batch should be added to
   * @param array $composedBatch
   *   The list of email address to be submitted to MailChimp
   *
   * @return array
   *   A list of the RabbitMQ queue entry IDs that have been successfully
   *   submitted to MailChimp.
   */
  public function commitBatch()
  {
    $batch = $this->mailchimpLists->processBatchOperations();
    if (!$batch || !$batch->id) {
      throw new Exception('Batch commit returned error response: ' . print_r(response) . PHP_EOL);
    }

    // Wait for batch status response, max 5 minutes:
    echo 'Waiting for batch ' . $batch->id . ' results' . PHP_EOL;
    $counter = 0;
    $processed = false;
    while (!$processed && $counter < 300) {
      sleep(1);
      $results = $this->mailchimpLists->getBatchOperation($batch->id);
      $processed = !!$results->completed_at;
      $counter++;
    }

    if (!$processed) {
      throw new Exception('Batch: ' . $batch->id . ' took longer than 5 minutes to process' . PHP_EOL);
    }

    // Exit if no errors:
    if (!$results->errored_operations) {
      $this->statHat->ezCount('MB_Toolbox: MB_MailChimp: submitBatchSubscribe', 1);
      return ['result' => true];
    }

    // Download error messages results:
    




    echo '- MB_MailChimp->submitBatchToMailChimp: results: ' . print_r($response, true), PHP_EOL;

    // Trap errors
    if (isset($results['error'])) {
      throw new Exception('Call to lists/batch-subscribe returned error response: ' . $results['name'] . ': ' .  $results['error']);
    } elseif ($results == 0) {
      throw new Exception('Hmmm: No results returned from Mailchimp lists/batch-subscribe submisson. This often happens when the batch size is too large. ');
    }


    return $results;
  }

}
