<?php
/*
 * Message Broker ("MB") MailChimp class library of functionality related to
 * the MailChimp service: https://apidocs.mailchimp.com/api/2.0/
 */

namespace DoSomething\MB_Toolbox;

use \Drewm\MailChimp;
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
     * External service MailChimp used to administer and send to emailing lists..
     *
     * @var object
     */
    private $mailChimp;

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
        $this->mailChimp = new MailChimp($apiKey);

        $this->mbConfig = MB_Configuration::getInstance();
        $this->statHat = $this->mbConfig->getProperty('statHat');
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
    public function submitBatchSubscribe($listID, $composedBatch = [])
    {

        $results = $this->mailChimp->call("lists/batch-subscribe", [
            'id' => $listID,
            'batch' => $composedBatch,
            'double_optin' => false,
            'update_existing' => true,
            'replace_interests' => false
        ]);

        echo '- MB_MailChimp->submitBatchToMailChimp: results: ' . print_r($results, true), PHP_EOL;

        // Trap errors
        if (isset($results['error'])) {
            throw new Exception('Call to lists/batch-subscribe returned error response: ' . $results['name'] . ': ' .  $results['error']);
        } elseif ($results == 0) {
            throw new Exception('Hmmm: No results returned from Mailchimp lists/batch-subscribe submisson. This often happens when the batch size is too large. ');
        }

        $this->statHat->ezCount('MB_Toolbox: MB_MailChimp: submitBatchSubscribe', 1);

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
    public function submitSubscribe($listID, $composedItem = [])
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
   * @param array $newSubscribers
   *   The list of email address to be formatted
   *
   * @return array
   *   Array of email addresses formatted to meet MailChimp API requirements.
   */
    public function composeSubscriberSubmission($newSubscribers = [])
    {

        $composedSubscriberList = [];
        foreach ($newSubscribers as $newSubscriberCount => $newSubscriber) {
            if (isset($newSubscriber['birthdate']) && is_int($newSubscriber['birthdate'])) {
                $newSubscriber['birthdate_timestamp'] = $newSubscriber['birthdate'];
            }
            if (isset($newSubscriber['mobile']) && strlen($newSubscriber['mobile']) < 8) {
                unset($newSubscriber['mobile']);
            }

            // support different merge_vars for US vs UK
            if (isset($newSubscriber['application_id']) && $newSubscriber['application_id'] == 'UK') {
                $mergeVars = [
                    'FNAME' => isset($newSubscriber['fname']) ? $newSubscriber['fname'] : '',
                    'LNAME' => isset($newSubscriber['lname']) ? $newSubscriber['lname'] : '',
                    'MERGE3' => isset($newSubscriber['birthdate_timestamp']) ? date('d/m/Y',
                        $newSubscriber['birthdate_timestamp']) : '',
                ];
            } // Don't add Canadian users to MailChimp
            elseif (isset($newSubscriber['application_id']) && $newSubscriber['application_id'] == 'CA') {
                $this->channel->basic_ack($newSubscriber['mb_delivery_tag']);
                break;
            } else {
                $mergeVars = [
                    'UID' => isset($newSubscriber['uid']) ? $newSubscriber['uid'] : '',
                    'FNAME' => isset($newSubscriber['fname']) ? $newSubscriber['fname'] : '',
                    'MERGE3' => (isset($newSubscriber['fname']) && isset($newSubscriber['lname'])) ?
                        $newSubscriber['fname'] . $newSubscriber['lname'] : '',
                    'BDAY' => isset($newSubscriber['birthdate_timestamp']) ?
                        date('m/d', $newSubscriber['birthdate_timestamp']) : '',
                    'BDAYFULL' => isset($newSubscriber['birthdate_timestamp']) ?
                        date('m/d/Y', $newSubscriber['birthdate_timestamp']) : '',
                    'MOBILE' => isset($newSubscriber['mobile']) ? $newSubscriber['mobile'] : '',
                ];
            }

            // Assign source interest group. Only support on main DoSomething Members List
            // @todo: Support "id" as a variable. Perhaps a config setting keyed on countries / global.
            if (isset($newSubscriber['source']) &&
                isset($newSubscriber['user_country']) &&
                strtoupper($newSubscriber['user_country']) == 'US')
            {
                $mergeVars['groupings'] = [
                    0 => [
                      'id' => 10657,  // DoSomething Memebers -> Import Source
                      'groups' => [
                          $newSubscriber['source']
                      ]
                    ],
                ];
            }

            $composedSubscriberList[$newSubscriberCount] = [
                'email' => [
                  'email' => $newSubscriber['email']
                ],
                'merge_vars' => $mergeVars
            ];
        }

        return $composedSubscriberList;
    }

  /**
   * Gather account information froma specific list
   *
   * Reference: http://apidocs.mailchimp.com/api/2.0/lists/member-info.php
   *
   * @param string email
   *   Target email address to lookup
   * @param string $listID
   *   The list to lookup the email address on.
   */
    public function memberInfo($email, $listID)
    {

        $mailchimpStatus = $this->mailChimp->call("/lists/member-info", [
            'id' => $listID,
            'emails' => [
                0 => [
                  'email' => $email
                ]
            ]
        ]);

        return $mailchimpStatus;
    }
}
