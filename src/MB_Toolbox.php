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
   * Authentication details from Drupal site
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

    // Check for cURL on server
    if (!is_callable('curl_init')) {
      throw new Exception("Error - PHP cURL extension is not enabled on the server. cURL is required by many of the methods in the mb-toolbox library.");
    }

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

    $tempPassword = str_replace(' ', '', $user->first_name) . '-Doer' . rand(1, 1000);
    $password = isset($user->password) ? $user->password : $tempPassword ;

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
    if ($port > 0 && is_numeric($port)) {
      $drupalAPIUrl .= ":{$port}";
    }
    $drupalAPIUrl .= self::DRUPAL_API . '/users';
    $result = $this->curlPOST($drupalAPIUrl, $post);

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('Requested createDrupalUser');
    $this->statHat->reportCount(1);

    if (is_array($result)) {
      echo $user->email . ' already a Drupal user.' . PHP_EOL;
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('Requested createDrupalUser - existing user');
      $this->statHat->reportCount(1);
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
    $curlUrl = $this->settings['ds_drupal_api_host'];
    $port = $this->settings['ds_drupal_api_port'];
    if ($port > 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }
    $curlUrl .= self::DRUPAL_API . '/users/' . $uid . '/password_reset_url';

    // $post value sent in cURL call intentionally empty due to the endpoint
    // expecting POST rather than GET where there's no POST values expected.
    $post = array();

    $result = $this->curlPOSTauth($curlUrl, $post);
    if (isset($result[0])) {
      $resetUrl = $result[0];
    }
    else {
      echo 'MB_Toolbox->getPasswordResetURL - ERROR: ' . print_r($resulkt, TRUE), PHP_EOL;
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

    $curlUrl = $this->settings['ds_drupal_api_host'];
    $port = $this->settings['ds_drupal_api_port'];
    if ($port > 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }
    $curlUrl .= self::DRUPAL_API . '/users/get_member_count';

    // $post value sent in cURL call intentionally empty due to the endpoint
    // expecting POST rather than GET where there's no POST values expected.
    $post = array();

    $result = $this->curlPOST($curlUrl, $post);
    if (isset($result[0]->readable)) {
      $memberCountFormatted = $result[0]->readable;
    }
    else {
      $memberCountFormatted = 'millions of';
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

    $this->statHat->clearAddedStatNames();
    $subscriptionLink = '';

    // Gather Drupal NID
    if ($drupalUID == NULL) {

      $curlUrl = $this->settings['ds_drupal_api_host'];
      $port = $this->settings['ds_drupal_api_port'];
      if ($port > 0 && is_numeric($port)) {
        $curlUrl .= ':' . (int) $port;
      }
      $curlUrl .= self::DRUPAL_API . '/users.json?parameters[email]=' .  urlencode($targetEmail);

      $result = $this->curlGETauth($curlUrl);

      if (isset($result[0][0]->uid)) {
        $drupalUID = (int) $result[0][0]->uid;
      }
      elseif ($result[1] == 200) {
        // @todo: Fix DELETE /user endpoint in mb-user-api
        // $result = $this->curlDELETE($curlUrl);
        echo 'ERROR - Drupal user not found by email: ' . $targetEmail, PHP_EOL;
        $subscriptionLink = 'ERROR - Drupal user not found by email';

        $this->statHat->addStatName('subscriptionsLinkGenerator ERROR - Drupal user not found by email');
      }
      else {
        echo 'Error making curlGETauth request to ' . $curlUrl, PHP_EOL;
        echo 'Returned results: ' . print_r($result, TRUE), PHP_EOL;
        $subscriptionLink = FALSE;

        $this->statHat->addStatName('subscriptionsLinkGenerator ERROR - curlGETauth call failed');
      }
    }

    // Generate link
    if ($drupalUID > 0) {

      // Build Subscription link path
      if (strlen($this->settings['subscriptions_url']) > 0) {
        $subscriptionsUrl = $this->settings['subscriptions_url'];
      }
      else {
        $subscriptionsUrl = $this->settings['subscriptions_ip'];
      }
      $port = $this->settings['subscriptions_port'];
      if ($port > 0 && is_numeric($port)) {
         $subscriptionsUrl .= ':' . (int) $port;
      }

      $keyData = urlencode($targetEmail) . ', ' . $drupalUID . ', ' . date('Y-m-d');
      $subscriptionLink = $subscriptionsUrl  . '?targetEmail=' . urlencode($targetEmail) . '&key=' . md5($keyData);

      $this->statHat->addStatName('subscriptionsLinkGenerator Success');
    }
    else {
      $subscriptionLink = FALSE;
    }

    $this->statHat->reportCount(1);
    return $subscriptionLink;
  }

  /**
   * cURL POSTs
   *
   * @param string $curlUrl
   *  The URL to POST to. Include domain and path.
   * @param array $post
   *  The values to POST.
   * @param boolean $isAuth
   *  Optional flag to denote if the method is being called from curlPOSTauth().
   *
   * @return object $result
   *   The results returned from the cURL call as an array:
   *   - [0]: Results in json format
   *   - [1]: Status code
   */
  public function curlPOST($curlUrl, $post, $isAuth = FALSE) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_POST, count($post));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch,CURLOPT_TIMEOUT, 20);

    // Only add token and cookie values to header when values are available and
    // the curlPOSTauth() method is making the POST request.
    if (isset($this->auth->token) && $isAuth) {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json',
          'X-CSRF-Token: ' . $this->auth->token,
          'Cookie: ' . $this->auth->session_name . '=' . $this->auth->sessid
        )
      );
    }
    elseif (strpos($curlUrl, 'api.dosomething') !== FALSE && isset($this->settings['northstar_api_id']) && isset($this->settings['northstar_api_key'])) {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json',
          'X-DS-Application-Id: ' . $this->settings['northstar_api_id'],
          'X-DS-REST-API-Key: ' . $this->settings['northstar_api_key'],
        )
      );
    }
    elseif (strpos($curlUrl, 'api.dosomething') !== FALSE) {
      trigger_error("MB_Toolbox->curlPOST() : Northstar settings not defined.", E_USER_ERROR);
    }
    else {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json'
        )
      );
    }

    $jsonResult = curl_exec($ch);
    echo '- curlGET jsonResult: ' . $jsonResult, PHP_EOL;

    $results[0] = json_decode($jsonResult);
    $results[1] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $results;
  }

  /**
   * cURL POSTs with authentication
   *
   * @param string $curlUrl
   *  The URL to POST to. Include domain and path.
   * @param array $post
   *  The values to POST.
   *
   * @return object $result
   *   The results returned from the cURL call.
   */
   public function curlPOSTauth($curlUrl, $post) {

    // Remove authentication until POST to /api/v1/auth/login is resolved
    if (!isset($this->auth)) {
      echo 'curlPOSTauth: this->auth not set, calling authenticate().', PHP_EOL;
      $this->authenticate();
    }

    $results = $this->curlPOST($curlUrl, $post, TRUE);

    return $results;
  }

  /**
   * cURL GETs
   *
   * @param string $curlUrl
   *  The URL to GET from. Include domain and path.
   * @param boolean $isAuth
   *  Optional flag to keep track of the current authencation state.
   *
   * @return boolean $results
   *  Response from cURL GET request.
   *
   * @return object $result
   *   The results returned from the cURL call.
   *   - [0]: Results in json format
   *   - [1]: Status code
   */
  public function curlGET($curlUrl, $isAuth = FALSE) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // Only add token and cookie values to header when values are available and
    // the curlPOSTauth() method is making the POST request.
    if (isset($this->auth->token) && $isAuth) {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json',
          'X-CSRF-Token: ' . $this->auth->token,
          'Cookie: ' . $this->auth->session_name . '=' . $this->auth->sessid
        )
      );
    }
    else {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json'
        )
      );
    }

    $jsonResult = curl_exec($ch);
    $results[0] = json_decode($jsonResult);
    $results[1] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $results;
  }

  /**
   * cURL GET with authentication
   *
   * @param string $curlUrl
   *  The URL to GET to. Include domain and path.
   *
   * @return object $result
   *   The results returned from the cURL call.
   */
  public function curlGETauth($curlUrl) {

    // Remove authentication until POST to /api/v1/auth/login is resolved
    if (!isset($this->auth)) {
      echo 'curlGETauth: this->auth not set, calling authenticate().', PHP_EOL;
      $this->authenticate();
    }

    $results = $this->curlGET($curlUrl, TRUE);

    return $results;
  }

  /**
   * cURL DELETEs
   *
   * @param string $curlUrl
   *  The URL to GET from. Include domain and path.
   * @param boolean $isAuth
   *  A flag to keep track of the current authencation state.
   *
   * @return object $result
   *   The results returned from the cURL call.
   *   - [0]: Results in json format
   *   - [1]: Status code
   */
  public function curlDELETE($curlUrl, $isAuth = FALSE) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // Only add token and cookie values to header when values are available and
    // the curlDELETEauth() method is making the POST request.
    if (isset($this->auth->token) && $isAuth) {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json',
          'X-CSRF-Token: ' . $this->auth->token,
          'Cookie: ' . $this->auth->session_name . '=' . $this->auth->sessid
        )
      );
    }
    else {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json'
        )
      );
    }

    $jsonResult = curl_exec($ch);
    $results[0] = json_decode($jsonResult);
    $results[1] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $results;
  }

  /**
   * Authenticate for API access
   */
  private function authenticate() {

    if (!empty($this->settings['ds_drupal_api_username']) && !empty($this->settings['ds_drupal_api_password'])) {
      $post = array(
        'username' => $this->settings['ds_drupal_api_username'],
        'password' => $this->settings['ds_drupal_api_password'],
      );
    }
    else {
      trigger_error("MB_Toolbox->authenticate() : username and/or password not defined.", E_USER_ERROR);
      exit(0);
    }

    // @todo: Abstract into it's own function
    $curlUrl = $this->settings['ds_drupal_api_host'];
    $port = $this->settings['ds_drupal_api_port'];
    if ($port > 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }

    // https://www.dosomething.org/api/v1/auth/login
    $curlUrl .= self::DRUPAL_API . '/auth/login';
    $auth = $this->curlPOST($curlUrl, $post);

    if ($auth[1] == 200) {
      $auth = $auth[0];
    }
    else {
      $auth = FALSE;
    }

    $this->auth = $auth;
  }

}
