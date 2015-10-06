<?php
/*
 * Message Broker ("MB") MailChimp class library of functionality related to
 * the MailChimp service: https://apidocs.mailchimp.com/api/2.0/
 */

namespace DoSomething\MB_Toolbox;

use \Drewm\MailChimp;

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
   * Make signup submission to MailChimp
   *
   * @param array $composedBatch
   *   The list of email address to be submitted to MailChimp
   *
   * @return array
   *   A list of the RabbitMQ queue entry IDs that have been successfully
   *   submitted to MailChimp.
   */
  public function submitBatchToMailChimp($composedBatch = array()) {

    // Debugging
    // $results1 = $this->mailChimp->call("lists/list", array());

    // DS Domestic: f2fab1dfd4
    // Innternational: 8e7844f6dd
    // $results2 = $this->mailChimp->call("lists/interest-groupings", array('id' => '8e7844f6dd'));

    // batchSubscribe($id, $batch, $double_optin=true, $update_existing=false, $replace_interests=true)
    // replace_interests: optional - flag to determine whether we replace the
    // interest groups with the updated groups provided, or we add the provided
    // groups to the member's interest groups (optional, defaults to true)
        // Lookup list details including "mailchimp_list_id"
    // -> 71893 "Do Something Members" is f2fab1dfd4 (who knows why?!?)

    $results = $this->mailChimp->call("lists/batch-subscribe", array(
      'id' => $this->settings['mailchimp_int_list_id'],
      'batch' => $composedBatch,
      'double_optin' => FALSE,
      'update_existing' => TRUE,
      'replace_interests' => FALSE
    ));

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('submitBatchToMailChimp');
    $this->statHat->reportCount(count($composedBatch));

    return $results;
  }

  /**
   * Make single signup submission to MailChimp. Typically used for resubscribes.
   *
   * @param array $composedItem
   *   The the details of an email address to be submitted to MailChimp
   *
   * @return array
   *   A list of the RabbitMQ queue entry IDs that have been successfully
   *   submitted to MailChimp.
   */
  public function submitToMailChimp($composedItem = array()) {

    $results = $this->mailChimp->call("lists/subscribe", array(
      'id' => $this->settings['mailchimp_list_id'],
      'email' => array(
        'email' => $composedItem['email']['email']
        ),
      'merge_vars' => $composedItem['merge_vars'],
      'double_optin' => FALSE,
      'update_existing' => TRUE,
      'replace_interests' => FALSE,
      'send_welcome' => FALSE,
    ));

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('submitToMailChimp');
    $this->statHat->reportCount(1);

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
  private function composeSubscriberSubmission($newSubscribers = array()) {

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
      );

      if (isset($newSubscriber['source'])) {
        $mergeVars['groupings'] = array(
          0 => array(
            'id' => 10657,  // DoSomething Memebers -> Import Source
            'groups' => array($newSubscriber['source'])
          ),
        );
      }
      $composedSubscriberList[$newSubscriberCount]['merge_vars'] = $mergeVars;

    }

    return $composedSubscriberList;
  }

}
