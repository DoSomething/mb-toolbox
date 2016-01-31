<?php
/*
 * Message Broker ("MB") toolbox class library
 */

namespace DoSomething\MB_Toolbox;

use DoSomething\MB_Toolbox\MB_Toolbox_cURL;
use DoSomething\MBStatTracker\StatHat;

class MB_Toolbox
{

  const DRUPAL_API = '/api/v1';
  const DEFAULT_USERNAME = 'Doer';

  /**
   * Singleton instance of MB_Configuration application settings and service objects
   * @var object $mbConfig
   */
  private $mbConfig;

  /**
   * General tools for the Message Broker system related to making cURL calls.
   * @var object $mbToolboxcURL
   */
  private $mbToolboxcURL;

  /**
   * Setting from external service to track activity - StatHat.
   * @var object $statHat
   */
  private $statHat;

  /**
   * Authentication details from Drupal site
   * @var object $auth
   */
  private $auth;

  /**
   * Constructor for MB_Toolbox class. Test for curl_init library and gather
   * settings from mb_Comfiguration class.
   *
   * @return object
   */
  public function __construct() {

    $this->auth = NULL;

    // Check for cURL on server
    if (!is_callable('curl_init')) {
      throw new Exception("Error - PHP cURL extension is not enabled on the server. cURL is required by many of the methods in the mb-toolbox library.");
    }

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolboxcURL = new MB_Toolbox_cURL();
  }

  /**
   * Test if country code has a  DoSomething affiliate.
   *
   * Follow country code convention defined in:
   * http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements
   *
   * @param string $targetCountyCode
   *   Details about the user to create Drupal account for.
   *
   * @return boolean/array $foundAffiliate
   *   Test if supplied country code is a DoSomething affiliate country the URL
   *   to the affiliate site is returned vs boolean false if match is not found.
   */
  public function isDSAffiliate($targetCountyCode) {

    $foundAffiliate = FALSE;

    $affiliates = array(
      'GB', // United Kingdom
      'UK', // United Kingdom
      'CA', // Canada
      'ID', // Indonesia
      'BW', // Botswana
      'KE', // Kenya
      'GH', // Ghana
      'NG', // Nigeria
      'CD', // Congo, The Democratic Republic of the"
      'BR', // Brazil
      'MX', // Mexico
    );

    if (in_array($targetCountyCode, $affiliates)) {

      $affiliateURL = array(
        'GB' => 'https://uk.dosomething.org',        // United Kingdom
        'UK' => 'https://uk.dosomething.org',        // United Kingdom
        'CA' => 'https://canada.dosomething.org',    // Canada
        'ID' => 'https://indonesia.dosomething.org', // Indonesia
        'BW' => 'https://botswana.dosomething.org',  // Botswana
        'KE' => 'https://kenya.dosomething.org',     // Kenya
        'GH' => 'https://ghana.dosomething.org',     // Ghana
        'NG' => 'https://nigeria.dosomething.org',   // Nigeria
        'CD' => 'https://congo.dosomething.org',     // Congo, The Democratic Republic of the"
        'BR' => 'https://brazil.dosomething.org',    // Brazil
        'MX' => 'https://mexico.dosomething.org',    // Mexico
      );
      $foundAffiliate['url'] = $affiliateURL[$targetCountyCode];
      $this->statHat->ezCount('MB_Toolbox: isDSAffiliate Found');
    }
    else {
      $this->statHat->ezCount('MB_Toolbox: isDSAffiliate Not Found');
    }

    return $foundAffiliate;
  }

  /**
   * Send request to Drupal /api/v1/users end point to create a new user
   * account.
   *
   * @param object $user
   *   Details about the user to create Drupal account for.
   *
   *   - $user->email (required)
   *   - $user->password (required)
   *   - $user->user_registration_source (required)
   *
   *   - $user->first_name (optional)
   *   - $user->birthdate (optional)
   *   - $user->birthdate_timestamp (optional)
   *   - $user->last_name (optional)
   *
   * @return array
   *   Details of the new user account.
   */
  public function createDrupalUser($user) {

    // Ensure a valid email address
    if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {

      // Required
      $post = array(
        'email' => $user->email,
        'password' => $user->password,
        'user_registration_source' => isset($user->user_registration_source) ? $user->user_registration_source : '',
      );

      // Optional
      if (isset($user->first_name) && $user->first_name != '') {
        $post['first_name'] = $user->first_name;
      }
      if (isset($user->birthdate) && strpos($user->birthdate, '/') > 0 && strtotime($user->birthdate) != FALSE) {
        $post['birthdate'] = date('Y-m-d', strtotime($user->birthdate));
      }
      elseif (isset($user->birthdate) && is_int($user->birthdate)) {
        $post['birthdate'] = date('Y-m-d', $user->birthdate);
      }
      elseif (isset($user->birthdate_timestamp) && is_int($user->birthdate_timestamp)) {
        $post['birthdate'] = date('Y-m-d', $user->birthdate_timestamp);
      }
      if (isset($user->last_name) && $user->last_name != '') {
        $post['last_name'] = $user->last_name;
      }

      $ch = curl_init();
      $dsDrupalAPIConfig = $this->mbConfig->getProperty('ds_drupal_api_config');
      $drupalAPIUrl =  $dsDrupalAPIConfig['host'];
      $port = $dsDrupalAPIConfig['port'];
      if ($port > 0 && is_numeric($port)) {
        $drupalAPIUrl .= ":{$port}";
      }
      $drupalAPIUrl .= self::DRUPAL_API . '/users';
      $result = $this->mbToolboxcURL->curlPOST($drupalAPIUrl, $post);
      $this->statHat->ezCount('MB_Toolbox: Requested createDrupalUser');

      if (is_array($result)) {
        $this->statHat->ezCount('MB_Toolbox: Requested createDrupalUser - existing user');
      }

    }
    else {
      echo 'ERROR - Invalid email address: ' . $user->email, PHP_EOL;
      $this->statHat->ezCount('MB_Toolbox: createDrupalUser - ERROR - Invalid email address');
      $result = FALSE;
      $password = '';
    }

    return array($result, $password);
  }

  /**
   * Get the users password reset link via Drupal end point.
   * https://github.com/DoSomething/dosomething/wiki/API#get-password-reset-url
   *
   * POST https://beta.dosomething.org/api/v1/users/[uid]/password_reset_url
   *
   * @param int $uid
   *    UID of the user were getting the reset URL for.
   *
   * @return string $resetUrl
   *    The string supplied by the Drupal endpoint /password_reset_url or NULL on failure
   */
  public function getPasswordResetURL($uid) {

    $dsDrupalAPIConfig = $this->mbConfig->getProperty('ds_drupal_api_config');
    $curlUrl = $dsDrupalAPIConfig['host'];
    $port = $dsDrupalAPIConfig['port'];
    if ($port > 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }
    $curlUrl .= self::DRUPAL_API . '/users/' . $uid . '/password_reset_url';

    // $post value sent in cURL call intentionally empty due to the endpoint
    // expecting POST rather than GET where there's no POST values expected.
    $post = array();

    $result = $this->mbToolboxcURL->curlPOSTauth($curlUrl, $post);
    if (isset($result[0][0])) {
      $resetUrl = $result[0][0];
      $this->statHat->ezCount('MB_Toolbox: getPasswordResetURL');
    }
    else {
      echo 'MB_Toolbox->getPasswordResetURL - ERROR: ' . print_r($result, TRUE), PHP_EOL;
      $this->statHat->ezCount('MB_Toolbox: getPasswordResetURL ERROR');
      $resetUrl = NULL;
    }
    return $resetUrl;
  }

  /**
   * Gather current member count via Drupal end point.
   * https://github.com/DoSomething/dosomething/wiki/API#get-member-count
   *
   * POST https://beta.dosomething.org/api/v1/users/get_member_count
   *
   * @return string $memberCountFormatted
   *   The string supplied by the Drupal endpoint /get_member_count or NULL
   *   on failure.
   */
  public function getDSMemberCount() {

    $dsDrupalAPIConfig = $this->mbConfig->getProperty('ds_drupal_api_config');
    $curlUrl = $dsDrupalAPIConfig['host'];
    if (isset($dsDrupalAPIConfig['port'])) {
      $port = $dsDrupalAPIConfig['port'];
      if ($port > 0 && is_numeric($port)) {
        $curlUrl .= ':' . (int) $port;
      }
    }
    $curlUrl .= self::DRUPAL_API . '/users/get_member_count';

    // $post value sent in cURL call intentionally empty due to the endpoint
    // expecting POST rather than GET where there's no POST values expected.
    $post = array();

    $result = $this->mbToolboxcURL->curlPOST($curlUrl, $post);
    if (isset($result[0]->readable)) {
      $memberCountFormatted = $result[0]->readable;
      $this->statHat->ezCount('MB_Toolbox: getDSMemberCount');
    }
    else {
      $memberCountFormatted = 'millions of';
      $this->statHat->ezCount('MB_Toolbox: getDSMemberCount ERROR');
    }
    return $memberCountFormatted;
  }

  /**
   * Generate user specific link URL to user subscription setting
   * (http://subscriptions.dosomething.org) web page. The page is an interface
   * to allow users to subscribe/unsubscribe to different types of email
   * messaging.
   *
   * @param string $targetEmail
   *   The email address to generate the subscription URL for.
   * @param integer $drupalNID
   *   Supplying the related Drupal NID incomplination with the $targetEmail will
   *   result in an unsubscription link generation that will not require a call
   *   to the Drupal app to lookup the NID by email. This is important because the
   *   valume for this call that the Drupal app will process is very limited (maxes
   *   at 200 calls per session key).
   *
   * @return string $subscription_link
   *   The URL to the user subscription settings web page. The link includes a
   *   key md5 hash value to limit page access to authorized users who have
   *   received an email from the lists the subscription page administers.
   */
  public function subscriptionsLinkGenerator($targetEmail, $drupalUID = NULL) {

    $subscriptionLink = '';
    $dsDrupalAPIConfig = $this->mbConfig->getProperty('ds_drupal_api_config');
    $curlUrl = $dsDrupalAPIConfig['host'];
    $port = $dsDrupalAPIConfig['port'];
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }

    // Gather Drupal NID
    if ($drupalUID == NULL) {
      $curlUrl .= self::DRUPAL_API . '/users.json?parameters[email]=' .  urlencode($targetEmail);

      $result = $this->mbToolboxcURL->curlGETauth($curlUrl);

      if (isset($result[0][0]->uid)) {
        $drupalUID = (int) $result[0][0]->uid;
      }
      elseif ($result[1] == 200) {

        echo '- ERROR - Drupal user not found by email: ' .  $targetEmail, PHP_EOL;
        $subscriptionLink = 'ERROR - Drupal user not found by email.';
        $this->statHat->ezCount('MB_Toolbox: subscriptionsLinkGenerator - ERROR - Drupal user not found by email');
      }
      else {
        echo 'Error making curlGETauth request to ' . $curlUrl, PHP_EOL;
        echo 'Returned results: ' . print_r($result, TRUE), PHP_EOL;
        $subscriptionLink = FALSE;

        $this->statHat->ezCount('MB_Toolbox: subscriptionsLinkGenerator - ERROR - curlGETauth call failed');
      }
    }

    // Generate link
    if ($drupalUID > 0) {

      // Build Subscription link path
      $subscriptions = $this->mbConfig->getProperty('subscriptions_config');
      if (strlen($subscriptions['host']) > 0) {
        $subscriptionsUrl = $subscriptions['host'];
      }
      else {
        $subscriptionsUrl = $subscriptions['ip'];
      }
      $port = $subscriptions['port'];
      if ($port > 0 && is_numeric($port)) {
         $subscriptionsUrl .= ':' . (int) $port;
      }

      $keyData = urlencode($targetEmail) . ', ' . $drupalUID . ', ' . date('Y-m-d');
      $subscriptionLink = $subscriptionsUrl  . '?targetEmail=' . urlencode($targetEmail) . '&key=' . md5($keyData);

      $this->statHat->ezCount('MB_Toolbox: subscriptionsLinkGenerator - Success');
    }

    return $subscriptionLink;
  }

  /**
   * countryFromTemplateName(): Extract country code from email template string. The last characters in string are
   * country specific. If last character is "-" the template name is invalid, default to "US" as country.
   *
   * @param string $emailTemplate
   *   The name of the template defined in the message transactional request.
   *
   * @return string $country
   *   A two letter country code.
   */
  public function countryFromTemplateName($emailTemplate) {

    // Trap NULL values for country code. Ex: "mb-cgg2015-vote-"
    if (substr($emailTemplate, strlen($emailTemplate) - 1) == "-") {
      echo '- WARNING countryFromTemplateName() defaulting to country: US as template name was invalid. $emailTemplate: ' . $emailTemplate, PHP_EOL;
      $country = 'US';
    }
    else {
      $templateBits = explode('-', $emailTemplate);
      $country = $templateBits[count($templateBits) - 1];
    }

    return $country;
  }

}
