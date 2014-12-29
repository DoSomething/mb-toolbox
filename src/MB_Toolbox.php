<?php
/*
 * Message Broker ("MB") toolbox class library
 */

namespace DoSomething\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;

class MB_Toolbox
{

  const DRUPAL_API = '/api/v1';

  /**
   * Service settings
   *
   * @var array
   */
  private $settings;

  /**
   * Setting from external service to track activity - StatHat.
   *
   * @var object
   */
  private $statHat;

  /**
   * Authenication details from Drupal site
   *
   * @var object
   */
  private $auth;

  /**
   * Constructor
   *
   * @param array $settings
   *   Connection and configuration settings common to the application
   *
   * @return object
   */
  public function __construct($settings) {
    $this->settings = $settings;
    $this->auth = NULL;

    $this->statHat = new StatHat($settings['stathat_ez_key'], 'MB_Toolbox:');
    $this->statHat->setIsProduction(TRUE);
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
   *   Test if supplied country code is a DoSomething affiliate country the url
   *   to the affiliate site is returned vs boolean false if match is not found.
   */
  public function isDSAffiliate($targetCountyCode) {

    $foundAffiliate = FALSE;

    $affiliates = array(
      'GB', // United Kingdom
      'CA', // Canada
      'ID', // Indonesia
      'BW', // Botswana
      'KE', // Kenya
      'GH', // Ghana
      'NG', // Nigeria
      'CD', // Congo, The Democratic Republic of the"
    );

    if (in_array($targetCountyCode, $affiliates)) {

      $affiliateURL = array(
        'GB' => 'https://uk.dosomething.org',        // United Kingdom
        'CA' => 'https://canada.dosomething.org',    // Canada
        'ID' => 'https://indonesia.dosomething.org', // Indonesia
        'BW' => 'https://botswana.dosomething.org',  // Botswana
        'KE' => 'https://kenya.dosomething.org',     // Kenya
        'GH' => 'https://ghana.dosomething.org',     // Ghana
        'NG' => 'https://nigeria.dosomething.org',   // Nigeria
        'CD' => 'https://congo.dosomething.org',     // Congo, The Democratic Republic of the"
      );
      $foundAffiliate['url'] = $affiliateURL[$targetCountyCode];
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('isDSAffiliate Found');
      $this->statHat->reportCount(1);
    }
    else {
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('isDSAffiliate Not Found');
      $this->statHat->reportCount(1);
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

    $password = isset($user->password) ? $user->password : "{$user->first_name}-Doer" . rand(1, 1000);
    // Required
    $post = array(
      'email' => $user->email,
      'password' => $password,
      'user_registration_source' => isset($user->user_registration_source) ? $user->user_registration_source : '',
    );

    // Optional
    if (isset($user->first_name)) {
      $post['first_name'] = $user->first_name;
    }
    if (isset($user->birthdate) && strpos($user->birthdate, '/') > 0) {
      $post['birthdate'] = date('Y-m-d', strtotime($user->birthdate));
    }
    elseif (isset($user->birthdate) && is_int($user->birthdate)) {
      $post['birthdate'] = date('Y-m-d', $user->birthdate);
    }
    elseif (isset($user->birthdate_timestamp) && is_int($user->birthdate_timestamp)) {
      $post['birthdate'] = date('Y-m-d', $user->birthdate_timestamp);
    }
    if (isset($user->last_name)) {
      $post['last_name'] = $user->last_name;
    }

    $ch = curl_init();
    $drupalAPIUrl = $this->settings['ds_drupal_api_host'];
    $port = $this->settings['ds_drupal_api_port'];
    if ($port != 0) {
      $drupalAPIUrl .= ":{$port}";
    }
    $drupalAPIUrl .= '/api/v1/users';
    $result = $this->curlPOSTauth($drupalAPIUrl, $post);

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('Requested createDrupalUser');
    $this->statHat->reportCount(1);

    if (is_array($result)) {
      echo("{$user->email} already a Drupal user." . PHP_EOL);
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('Requested createDrupalUser - existing user');
      $this->statHat->reportCount(1);
    }

    return array($result, $password);
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

    $curlUrl = $this->settings['ds_drupal_api_host'];
    $port = $this->settings['ds_drupal_api_port'];
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }
    $curlUrl .= '/api/v1/users/get_member_count';

    // $post value sent in cURL call intentionally empty due to the endpoint
    // expecting POST rather than GET where there's no POST values expected.
    $post = array();

    $result = $this->curlPOST($curlUrl, $post);
    if (isset($result->readable)) {
      $memberCountFormatted = $result->readable;
    }
    else {
      $memberCountFormatted = NULL;
    }
    return $memberCountFormatted;
  }

  /**
   * cURL POSTs with authenication
   *
   * @param string $curlUrl
   *  The URL to POST to. Include domain and path.
   *
   * @param array $post
   *  The values to POST.
   *
   * @return object $result
   *   The results retruned from the cURL call.
   */
  private function curlPOSTauth($curlUrl, $post) {

/* @todo: Remove authentication until POST to /api/v1/auth/login is resolved
    if (!isset($this->auth)) {
      $this->authenticate();
    }
*/
    $results = $this->curlPOST($curlUrl, $post);

    return $results;
  }

  /**
   * cURL POSTs
   *
   * @param string $curlUrl
   *  The URL to POST to. Include domain and path.
   *
   * @param array $post
   *  The values to POST.
   *
   * @return object $result
   *   The results retruned from the cURL call.
   */
  private function curlPOST($curlUrl, $post) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_POST, count($post));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER,
      array(
        'Content-type: application/json',
        'Accept: application/json'
      )
    );
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 20);

    if (isset($this->auth->token)) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization', 'OAuth ' . $this->auth->token));
    }

    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $jsonResult = curl_exec($ch);
    $results = json_decode($jsonResult);
    curl_close($ch);

    return $results;
  }

  /**
   * Authenticate for API access
   */
  private function authenticate() {

    $post = array(
      'username' => getenv("DS_DRUPAL_API_USERNAME"),
      'password' => getenv("DS_DRUPAL_API_PASSWORD")
    );
    // @todo: Abstract into it's own function
    $curlUrl = $this->settings['ds_drupal_api_host'];
    $port = $this->settings['ds_drupal_api_port'];
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }
    $curlUrl .= '/api/v1/auth/login';
    $auth = $this->curlPOST($curlUrl, $post);

    $this->auth = $auth;
  }

}
