<?php
/*
 * Message Broker ("MB") toolbox class library for functionality related to CURL requests.
 */

namespace DoSomething\MB_Toolbox;

use DoSomething\MBStatTracker\StatHat;
use \Exception;


/**
 * MB_Toolbox_cURL:
 */
class MB_Toolbox_cURL
{

  const DRUPAL_API = '/api/v1';
  const TIMEOUT = 0;

  /**
   * Singleton instance of MB_Configuration application settings and service objects
   *
   * @var object
   */
  protected $mbConfig;

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
  public function __construct() {

    $this->auth = NULL;

    // Check for cURL on server
    if (!is_callable('curl_init')) {
      throw new Exception("Error - PHP cURL extension is not enabled on the server. cURL is required by many of the methods in the mb-toolbox library.");
    }

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
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
    $northstarConfig = $this->mbConfig->getProperty('northstar_config', FALSE);
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
    elseif ($this->isNorthstar($northstarConfig, $curlUrl)) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-type: application/json',
          'Accept: application/json',
          'X-DS-Application-Id: ' . $northstarConfig['id'],
          'X-DS-REST-API-Key: ' . $northstarConfig['key'],
        ]
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
      $this->authenticate();
    }

    $results = $this->curlGET($curlUrl, TRUE);

    return $results;
  }

  /**
   * cURL GET Image (curlGETImage).
   *
   * @param string $imageUrl
   *  The URL to GET image from. Include domain and path.
   *
   * @return boolean $results
   *  Response from cURL GET request.
   *
   * @return object $result
   *   The results returned from the cURL call.
   *   - [0]: Results in json format
   *   - [1]: Status code
   */
  static public function curlGETImage($imageUrl) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $imageUrl);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

    $results[0] = curl_exec($ch);
    $results[1] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $results;
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
    $northstarConfig = $this->mbConfig->getProperty('northstar_config', FALSE);
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
    elseif ($this->isNorthstar($northstarConfig, $curlUrl)) {
      curl_setopt($ch, CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'Accept: application/json',
          'X-DS-Application-Id: ' . $northstarConfig['id'],
          'X-DS-REST-API-Key: ' . $northstarConfig['key'],
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
      $this->authenticate();
    }

    $results = $this->curlPOST($curlUrl, $post, TRUE);

    return $results;
  }

  /**
   * cURL DELETE
   *
   * @param string $curlUrl
   *  The URL to DELETE from. Include domain and path.
   *
   * @return object $result
   *   The results returned from the cURL call.
   *   - [0]: Results in json format
   *   - [1]: Status code
   */
  public function curlDELETE($curlUrl) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Accept: application/json',
      'Content-Type: application/json',
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $jsonResult = curl_exec($ch);
    $results[0] = json_decode($jsonResult);
    $results[1] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $results;
  }

  /**
   * cURL DELETE with authentication
   *
   * @param string $curlUrl
   *  The URL to DELETE to. Include domain and path.
   *
   * @return object $result
   *   The results returned from the cURL call.
   */
  public function curlDELETEauth($curlUrl) {

    // Remove authentication until POST to /api/v1/auth/login is resolved
    if (!isset($this->auth)) {
      $this->authenticate();
    }

    $results = $this->curlDELETE($curlUrl, TRUE);

    return $results;
  }

  /**
   * Authenticate for API access
   */
  private function authenticate() {

    $dsDrupalAPIConfig = $this->mbConfig->getProperty('ds_drupal_api_config');

    if (!empty($dsDrupalAPIConfig['username']) && !empty($dsDrupalAPIConfig['password'])) {
      $post = array(
        'username' => $dsDrupalAPIConfig['username'],
        'password' => $dsDrupalAPIConfig['password'],
      );
    }
    else {
      trigger_error("MB_Toolbox->authenticate() : username and/or password not defined.", E_USER_ERROR);
      exit(0);
    }

    // https://www.dosomething.org/api/v1/auth/login
    $curlUrl  = $this->buildcURL($dsDrupalAPIConfig);
    $curlUrl .= self::DRUPAL_API . '/auth/login';
    $auth = $this->curlPOST($curlUrl, $post);

    if ($auth[1] == 200) {
      $auth = $auth[0];
    }
    else {
      ECHO 'ERROR - Failed to get auth creds: ' . $curlUrl . ' with POST: ' . print_r($post, TRUE), PHP_EOL;
      $auth = FALSE;
    }

    $this->auth = $auth;
  }

  /**
   * buildURL - Common construction utility for URLs of API paths.
   *
   * @todo: Move to MB_Toolbox_cURL class.
   *
   * @param array $settings
   *   "host" and "port" setting
   */
  public function buildcURL($settings) {

    if (isset($settings['host'])) {
      $curlUrl = $settings['host'];
      $port = $settings['port'];
      if ($port > 0 && is_numeric($port)) {
        $curlUrl .= ':' . (int) $port;
      }
      return $curlUrl;
    }
    else {
      throw new Exception('buildcURL required host setting missing.');
    }
  }

  /**
   * Test if the Northstar configuration settings are valid and the cURL is for Northstar.
   *
   * @param array $northstarConfig
   *   Configuration settings for connecting to Northstar API
   * @param string $curlUrl
   *   A cURL path.
   */
  private function isNorthstar($northstarConfig, $curlUrl) {

    // Confirm each of the required config settings are available
    if (empty($northstarConfig['host'])) {
      return false;
    }
    if (empty($northstarConfig['id'])) {
      return false;
    }
    if (empty($northstarConfig['key'])) {
      return false;
    }

    // Validate cURL as being for Northstar
    if (strpos($curlUrl, $northstarConfig['host']) === false) {
      return false;
    }

    return true;
  }

}
