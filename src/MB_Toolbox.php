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
   * Test if country code has a  DoSomething affiliate.
   *
   * Follow country code convention defined in:
   * http://dev.maxmind.com/geoip/legacy/codes/iso3166/
   *
   * @param string $targetCountyCode
   *   Details about the user to create Drupal account for.
   *
   * @return boolean $foundAffiliate
   *   Test if supplied country code is a DoSomething affiliate country.
   */
  private function isDSAffiliate($targetCountyCode) {

    $foundAffiliate = FALSE;

    $affiliates = array(
      'US', // United States
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
      $foundAffiliate = TRUE;
    }

  return $foundAffiliate;
}
