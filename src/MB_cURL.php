<?php
/*
 * Message Broker ("MB") toolbox class library
 */

namespace DoSomething\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;

class MB_cURL
{

  const DRUPAL_API = '/api/v1';
  const TIMEOUT = 0;

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
   * cURL GET Image (curlGETImage).
   *
   * @param string $curlUrl
   *  The URL to GET from. Include domain and path.
   *
   * @return boolean $results
   *  Response from cURL GET request.
   *
   * @return object $result
   *   The results returned from the cURL call.
   *   - [0]: Results in json format
   *   - [1]: Status code
   */
  public function curlGETImage($curlUrl) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

    $results[0] = curl_exec($ch);
    $results[1] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $results;
  }

}
