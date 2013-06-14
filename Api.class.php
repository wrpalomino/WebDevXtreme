<?php 
/**
 * Class Api: Define the interface between third service providers and the main application
 * Created at 11/11/2012
 *
 * @author William Palomino
 * @version 2.0
 */

class Api 
{
  protected $api_key = '';
  protected $username = '';
  protected $password = '';
  protected $secret = '';
  protected $url = '';
  protected $service = '';
  protected $send_message_status = '';
  
  protected $gateway = null;
  
  /**
   * Class Constructor
   *
   * @param  service  third party service name
   * @param  api_key  alternative to password and username to connect to the third party service
   * @param  username to connect to the third party service
   * @param  password to connect to the third party service
   * @param  secret   alternative to password and username to connect to the third party service
   * @return          NONE
   */
  public function __construct($service, $api_key=null, $username=null, $password=null, $secret=null)
  {
    if (sfConfig::get("app_".$service)) {
      $this->gateway = sfConfig::get("app_".$service);
      $this->service = $service;
    }
    $this->api_key = $api_key;
    $this->username = $username;
    $this->password = $password;
    $this->secret = $secret;
  }
  
  /**
   * Set url if gateway is OK; otherwise, set message status with error
   *
   * @return NONE
   */
  public function checkGateway()
  {
    $this->send_message_status = '';
        
    if ($this->gateway) {
      $this->url = $this->gateway['url'];
    }
    else {
      $this->send_message_status = "Error: No Gateway defined!";
    }
  }
  
  
  /**
   * Get the the params to be used for connection with third party service. Some will be the default params but the 
   * user can provide customized (variable) params too.
   *
   * @param  params   array with cutomized params
   * @return          array with the params to be used for connection with third party service
   */
  public function getParams($params)
  {
    $def_params = $this->gateway['params'];
      
    foreach ($params as $k => $v) {
      if (isset($def_params[$k])) $def_params[$k] = urlencode($v);
      else $this->send_message_status = "bad param for gateway";
    }
    foreach ($def_params as $k => $v) {
      //echo $k .'=>' .$v.'<br/>';
      if ($v == ".")  $v = "";  // clear empty values
      if (trim($v) == "") $this->send_message_status = "missing param for gateway";
    }
    return $def_params;
  }
  
  
  public function checkSettings($params)
  {
    $this->checkGateway();
    if ($this->send_message_status != '') return null;
    
    $postfields = $this->getParams($params);
    if ($this->send_message_status != '') return null;
    
    return $postfields;
  }
  
  
  public function getSendMessageStatus()
  {
    return $this->send_message_status;
  }
  
  
  public function executeDefaultCurl($postfields)
  {
    if (!$curld = curl_init()) {
      $this->send_message_status = "Could not initialize cURL session.";
      return false;
    }
    curl_setopt($curld, CURLOPT_POST, true);
    curl_setopt($curld, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($curld, CURLOPT_URL, $this->url);
    curl_setopt($curld, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curld);
    curl_close ($curld);
    $out = explode('|', $output);

    $this->send_message_status = "Message Status:".$out[0];
    return true;
  }
}



/**
 * Class SmsBroadcast: Define the interface for SMS BroadCast Service
 * Created at 20/11/2012
 *
 * @author William Palomino
 * @version 2.0
 */
class SmsBroadcast extends Api
{
 
  /**
   * Make the call to the service to send an SMS online. Also, it will set the message status for the outcome
   *
   * @param  params   array with params to be used. It could be empty (previous defined params will be used)
   * @return          boolean flag (Success or failure of the call)
   */
  public function makeCall($params=array())
  {
    $postfields = $this->checkSettings($params);
    if ($postfields === null) return false;
    
    $url_params_arr = array();
    foreach ($postfields as $k => $v)  $url_params_arr[] = $k."=".$v;
    $this->url.= "?".implode("&", $url_params_arr);
    $this->send_message_status = file_get_contents($this->url);

    if ($this->send_message_status != "Your message was sent.") {
      $this->send_message_status = "The message failed. The reason is: $this->send_message_status";
      return false;
    }
    return true;
  }
  
  /**
   * TO BE IMPLEMENTED. Request the credit balance of the account
   *
   * @param  params   array with params to be used. It could be empty (previous defined params will be used)
   * @return          boolean flag (Success or failure of the call)
   */
  public function requestCreditBalance($params=array())
  {
    
  }
}



/**
 * Class ICloud: Define the interface for ICloud Service
 * Created at 26/11/2012
 *
 * @author William Palomino
 * @version 2.0
 */
class ICloud extends Api
{
  
  /**
   * Make the call to save a new event (calendar date) in the ICalendar of the user. Also, it will set the message 
   * status for the outcome
   *
   * @param  params   array with params to be used. It could be empty (previous defined params will be used)
   * @return          boolean flag (Success or failure of the call)
   */
  public function saveEvent($params=array())
  {
    $postfields = $this->checkSettings($params);
    if ($postfields === null) return false;
    
    $eid = urldecode($postfields['eid']);
    $calendar_name = 'M2CD-2-1-F2FE953C-041B-436F-8BA2-4355CADE89C4'; // verified name: work
    $this->url = 'https://'.$postfields['server'].'-caldav.icloud.com/'.$postfields['icloudid'].'/calendars/'.$calendar_name.'/' . $eid . '.ics';
    
    $userpwd = $postfields['username'] .":". $postfields['password'];
    $description = urldecode($postfields['description']);
    $summary = urldecode($postfields['summary']);
    /*$tstart = gmdate("Ymd\THis\Z", strtotime("now"));
    $tend = gmdate("Ymd\THis\Z", strtotime("now"));
    $tstamp = gmdate("Ymd\THis\Z");*/
    $tstart = $postfields['tstart']; //date("Ymd\THis\Z", strtotime("now"));
    $tend = $postfields['tend']; //date("Ymd\THis\Z", strtotime("now"));
    $tstamp = date("Ymd\THis\Z", strtotime("now"));

$body = <<<__EOD
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Australia/Sydney
X-LIC-LOCATION:Australia/Sydney
BEGIN:STANDARD
TZNAME:AUS Eastern Standard Time
TZOFFSETFROM:+1100
TZOFFSETTO:-1000
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:$tstamp
DTSTART;TZID="Australia/Sydney":$tstart
DTEND;TZID="Australia/Sydney":$tend
UID:$eid
DESCRIPTION;ENCODING=QUOTED-PRINTABLE:$description
LOCATION:Court
SUMMARY:$summary
END:VEVENT
END:VCALENDAR
__EOD;

    $headers = array(
      'Content-Type: text/calendar; charset=utf-8',
      'If-None-Match: *',
      'Expect: ',
      'Content-Length: '.strlen($body),
    );
    
    
    if (!$curld = curl_init()) {
      $this->send_message_status = "Could not initialize cURL session.";
      return false;
    }
    $curld = curl_init();
    curl_setopt($curld, CURLOPT_URL, $this->url);
    curl_setopt($curld, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curld, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curld, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curld, CURLOPT_USERPWD, $userpwd);
    curl_setopt($curld, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($curld, CURLOPT_POSTFIELDS, $body);

    $output = curl_exec($curld);
    curl_close ($curld);
    $out = explode('|', $output);

    if ($out[0] != '') {
      $this->send_message_status = "Message Status: ".$out[0];
      return false;
    }
    else {
      $this->send_message_status = "Message Status: Event has been added to iCalendar Successfully!";
      return true;
    }
  }
}
?>
