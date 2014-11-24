<?php
/*
 * Message Broker ("MB") toolbox class library
 */

class MB_Toolbox
{

  /**
   * Setting from external service to track activity - StatHat.
   *
   * @var object
   */
  private $statHat;

  /**
   * Constructor
   *
   * @param array $config
   *   Connection and configuration settings common to the application
   *
   * @return object
   */
  public function __construct($config = array()) {
    $this->statHat = new StatHat($settings['stathat_ez_key'], 'MB_Toolbox:');
    $this->statHat->setIsProduction(FALSE);
  }

  /**
   * Send request to Drupal /api/v1/users end point to create a new user
   * account.
   *
   * @param array $user
   *   Details about the user to create Drupal account for.
   *   - $user->email (required)
   *   - $user->first_name (required)
   *
   * @return array
   *   Details of the new user account.
   */
  public function createDrupalUser($user) {

    $password = isset($user->password) ? $user->password : $user->first_name . '-Doer' . rand(1, 1000);
    $post = array(
      'email' => $user->email,
      'password' => $password,
    );
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
    if (isset($user->user_registration_source)) {
      $post['user_registration_source'] = $user->user_registration_source;
    }

    $ch = curl_init();
    $drupalAPIUrl = getenv('DS_DRUPAL_API_HOST');
    $port = getenv('DS_DRUPAL_API_PORT');
    if ($port != 0) {
      $drupalAPIUrl .= ':' . $port;
    }
    $drupalAPIUrl .= '/api/v1/users';

    curl_setopt($ch, CURLOPT_URL, $drupalAPIUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
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
    $jsonResult = curl_exec($ch);
    $result = json_decode($jsonResult);
    curl_close($ch);

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('Requested createDrupalUser');
    $this->statHat->reportCount(1);

    if (is_array($result)) {
      echo($user->email . ' already a Drupal user.' . PHP_EOL);
      $this->statHat->clearAddedStatNames();
      $this->statHat->addStatName('Requested createDrupalUser - existing user');
      $this->statHat->reportCount(1);
    }

    return array($result, $password);
  }

}
