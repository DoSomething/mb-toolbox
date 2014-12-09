<?php
/*
 * Message Broker ("MB") MailChimp class library of functionality related to
 * the MailChimp service: https://apidocs.mailchimp.com/api/2.0/
 */

namespace DoSomething\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;

class MB_MailChimp
{

  /**
   * Access credentials settings
   *
   * @var object
   */
  private $credentials;

  /**
   * Service settings
   *
   * @var array
   */
  private $settings;

  /**
   * External service MailChimp used to administer and send to emailing lists..
   *
   * @var object
   */
  private $mailChimp;

  /**
   * External service StatHat used to log application activity at definded
   * collection points.
   *
   * @var object
   */
  private $statHat;

  /**
   * Constructor for MBC_Mailchimp
   *
   * @param array $credentials
   *   Secret settings from mb-secure-config.inc
   *
   * @param array $config
   *   Configuration settings from mb-config.inc
   *
   * @param array $settings
   *   Settings from external services - Mailchimp
   */
  public function __construct($settings) {

    $this->settings = $settings;
    
        // Submit subscription to Mailchimp
    $this->mailChimp = new \Drewm\MailChimp($this->settings['mailchimp_apikey']);
    
    $this->statHat = new StatHat($settings['stathat_ez_key'], 'MB_MailChimp:');
    $this->statHat->setIsProduction(TRUE);
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
    // $listsListDebugging = $this->mailChimp->call("lists/list", array());

    // DS Domestic: f2fab1dfd4
    // Innternational: 8e7844f6dd
    // $listsInterestGroupingsDebugging = $this->mailChimp->call("lists/interest-groupings", array('id' => '8e7844f6dd'));

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

    return $results;
  }

}
