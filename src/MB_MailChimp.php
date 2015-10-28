<?php
/*
 * Message Broker ("MB") MailChimp class library of functionality related to
 * the MailChimp service: https://apidocs.mailchimp.com/api/2.0/
 */

namespace DoSomething\MB_Toolbox;

use \Drewm\MailChimp;
use \Exception;

class MB_MailChimp
{

  /**
   * External service MailChimp used to administer and send to emailing lists..
   *
   * @var object
   */
  private $mailChimp;

  /**
   * Constructor for MBC_Mailchimp
   *
   * @param array $apiKey
   *   The MailChimp API key to use for this instance of MailChip API activities.
   */
  public function __construct($apiKey) {
    $this->mailChimp = new MailChimp($apiKey);
  }
  
  /**
   * Make batch signup submission to MailChimp list: lists/batch-subscribe
   *
   * @param string $listID
   *   A unique ID that defines what MailChimp list the batch should be added to
   * @param array $composedBatch
   *   The list of email address to be submitted to MailChimp
   *
   * @return array
   *   A list of the RabbitMQ queue entry IDs that have been successfully
   *   submitted to MailChimp.
   */
  public function submitBatchSubscribe($listID, $composedBatch = array()) {

    $results = $this->mailChimp->call("lists/batch-subscribe", array(
      'id' => $listID,
      'batch' => $composedBatch,
      'double_optin' => FALSE,
      'update_existing' => TRUE,
      'replace_interests' => FALSE
    ));

    echo '- MB_MailChimp->submitBatchToMailChimp: results: ' . print_r($results, TRUE), PHP_EOL;

    // Trap errors
    if (isset($results['error'])) {
      throw new Exception('Call to lists/batch-subscribe returned error response: ' . $results['name'] . ': ' .  $results['error']);
    }
    elseif ($results == 0) {
      throw new Exception('Hmmm: No results returned from Mailchimp lists/batch-subscribe submisson. This often happens when the batch size is too large. ');
    }

    // @todo: Add StatHat tracking point: submitBatchToMailChimp

    return $results;
  }

  /**
   * Make single signup submission to MailChimp. Typically used for resubscribes.
   *
   * @param string $listID
   *   A unique ID that defines what MailChimp list the batch should be added to
   * @param array $composedItem
   *   The the details of an email address to be submitted to MailChimp
   *
   * @return array
   *   A list of the RabbitMQ queue entry IDs that have been successfully
   *   submitted to MailChimp.
   */
  public function submitSubscribe($listID, $composedItem = array()) {

    $results = $this->mailChimp->call("lists/subscribe", array(
      'id' => $listID,
      'email' => array(
        'email' => $composedItem['email']['email']
        ),
      'merge_vars' => $composedItem['merge_vars'],
      'double_optin' => FALSE,
      'update_existing' => TRUE,
      'replace_interests' => FALSE,
      'send_welcome' => FALSE,
    ));
    
    // Trap errors
    if (isset($results['error'])) {
      throw new Exception('Call to lists/subscribe returned error response: ' . $results['name'] . ': ' .  $results['error']);
    }
    elseif ($results == 0) {
      throw new Exception('Hmmm: No results returned from Mailchimp lists/subscribe submission.');
    }

    // @todo: Add StatHat tracking point: submitBatchToMailChimp

    return $results;
  }
  
    /**
   * Format email list to meet MailChimp API requirements for batchSubscribe
   *
   * @param array $newSubscribers
   *   The list of email address to be formatted
   *
   * @return array
   *   Array of email addresses formatted to meet MailChimp API requirements.
   */
  public function composeSubscriberSubmission($newSubscribers = array()) {

    $composedSubscriberList = array();
    foreach ($newSubscribers as $newSubscriberCount => $newSubscriber) {

      if (isset($newSubscriber['birthdate']) && is_int($newSubscriber['birthdate'])) {
        $newSubscriber['birthdate_timestamp'] = $newSubscriber['birthdate'];
      }
      if (isset($newSubscriber['mobile']) && strlen($newSubscriber['mobile']) < 8) {
        unset($newSubscriber['mobile']);
      }

      // support different merge_vars for US vs UK
      if (isset($newSubscriber['application_id']) && $newSubscriber['application_id'] == 'UK') {
        $mergeVars = array(
          'FNAME' => isset($newSubscriber['fname']) ? $newSubscriber['fname'] : '',
          'LNAME' => isset($newSubscriber['lname']) ? $newSubscriber['lname'] : '',
          'MERGE3' => isset($newSubscriber['birthdate_timestamp']) ? date('d/m/Y', $newSubscriber['birthdate_timestamp']) : '',
        );
      }
      // Don't add Canadian users to MailChimp
      elseif (isset($newSubscriber['application_id']) && $newSubscriber['application_id'] == 'CA') {
        $this->channel->basic_ack($newSubscriber['mb_delivery_tag']);
        break;
      }
      else {
        $mergeVars = array(
          'UID' => isset($newSubscriber['uid']) ? $newSubscriber['uid'] : '',
          'FNAME' => isset($newSubscriber['fname']) ? $newSubscriber['fname'] : '',
          'MMERGE3' => (isset($newSubscriber['fname']) && isset($newSubscriber['lname'])) ? $newSubscriber['fname'] . $newSubscriber['lname'] : '',
          'BDAY' => isset($newSubscriber['birthdate_timestamp']) ? date('m/d', $newSubscriber['birthdate_timestamp']) : '',
          'BDAYFULL' => isset($newSubscriber['birthdate_timestamp']) ? date('m/d/Y', $newSubscriber['birthdate_timestamp']) : '',
          'MMERGE7' => isset($newSubscriber['mobile']) ? $newSubscriber['mobile'] : '',
        );
      }

      $composedSubscriberList[$newSubscriberCount] = array(
        'email' => array(
          'email' => $newSubscriber['email']
        ),
        'merge_vars' => $mergeVars
      );

    }

    return $composedSubscriberList;
  }

}
